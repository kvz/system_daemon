<?php
/* vim: set noai expandtab tabstop=4 softtabstop=4 shiftwidth=4: */

/**
 * System_Daemon turns PHP-CLI scripts into daemons.
 * 
 * PHP version 5
 *
 * @category  System
 * @package   System_Daemon
 * @author    Kevin <kevin@vanzonneveld.net>
 * @copyright 2008 Kevin van Zonneveld (http://kevin.vanzonneveld.net)
 * @license   http://www.opensource.org/licenses/bsd-license.php New BSD Licence
 * @version   SVN: Release: $Id$
 * @link      http://trac.plutonia.nl/projects/system_daemon
 */

/**
 * System_Daemon. Create daemons with practicle functions like
 * $daemon->start()
 *
 * Requires PHP build with --enable-cli --with-pcntl --enable-shmop.
 * Only runs on *NIX systems, because Windows lacks of the pcntl ext.
 *
 * PHP version 5
 *
 * @category  System
 * @package   System_Daemon
 * @author    Kevin <kevin@vanzonneveld.net>
 * @copyright 2008 Kevin van Zonneveld (http://kevin.vanzonneveld.net)
 * @license   http://www.opensource.org/licenses/bsd-license.php New BSD Licence
 * @version   SVN: Release: $Id$
 * @link      http://trac.plutonia.nl/projects/system_daemon
 * 
 */
class System_Daemon
{
    /***************************************************************************
    *** VARS
    ****************************************************************************/
    /**
     * Wether or not to run this class standalone, or as a part of PEAR
     *
     * @var boolean
     */
    public $pear = true;
    
    /**
     * The application name e.g.: logparser
     *
     * @var string
     */
    public $app_name;

    /**
     * The home dirpath e.g.: /usr/local/logparser. Defaults to __FILE__ dir
     *
     * @var string
     */
    public $app_dir;

    /**
     * The executeble daemon file, e.g.: logparser.php. 
     * Defaults to: SCRIPT_NAME basename
     *
     * @var string
     */
    public $app_executable;

    /**
     * Daemon description. Required for forging init.d script
     *
     * @var string
     */
    public $app_description;

    /**
     * Author name. Required for forging init.d script
     *
     * @var string
     */
    public $author_name;

    /**
     * Author email. Required for forging init.d script
     *
     * @var string
     */
    public $author_email;
  
    /**
     * The pid filepath , e.g.: /var/run/logparser.pid. 
     * Defaults to: /var/run/${app_name}.pid
     *
     * @var string
     */
    public $pid_filepath;

    /**
     * The log filepath , e.g.: /var/log/logparser_daemon.log. 
     * Defaults to: /var/log/${app_name}_daemon.log
     *
     * @var string
     */
    public $log_filepath;

    /**
     * The user id under which to run the process.
     * Defaults to: root
     * 
     * @var string
     */
    public $uid = 0;

    /**
     * The group id under which to run the process.
     * Defaults to: root
     *
     * @var string
     */
    public $gid = 0;


    /**
     * Wether the our daemon is being killed, you might 
     * want to include this in your loop
     *
     * @var boolean
     */
    public $is_dying = false;
    
    /**
     * Kill daemon if it cannot assume the identity (uid + gid)
     *
     * @var string
     */
    public $die_on_identitycrisis = true;

    /**
     * Available log levels
     *
     * @var array
     */
    private $_log_levels = array(
        0=> "debug",
        1=> "info",
        2=> "waring",
        3=> "critical",
        4=> "fatal"
    );

    /**
     * Keep track of passed signals
     *
     * @var array
     */
    private $_signals = array();

    /**
     * The current process identifier
     *
     * @var integer
     */
    private $_pid = 0;

    /**
     * Wether the current process is a forked child
     *
     * @var boolean
     */
    private $_is_child = false;

    /**
     * Wether all the variables have been initialized
     *
     * @var boolean
     */
    private $_is_initialized = false;

    /**
     * Cache that holds values of some functions 
     * for performance gain
     *
     * @var array
     */
    private $_fnc_cache = array();


    /**
     * Constructs a System_Daemon object.
     *
     * @param string  $app_name The unix name of your daemon application.
     * @param boolean $pear     Wether or not to run as a part of pear.
     *
     * @see start()
     */
    public function __construct($app_name, $pear = true)
    {
        $this->app_name = $app_name;
        $this->pear     = $pear;
        
        // to run as a part of pear
        if ( $this->pear ) {
            // conditional so use include
            include_once "PEAR/Exception.php";
        }
        
        // check the PHP configuration
        if (!defined('SIGHUP')) {
            trigger_error('PHP is compiled without --enable-pcntl directive', 
                E_USER_ERROR);
        }        
        
        ini_set("max_execution_time", "0");
        ini_set("max_input_time", "0");
        set_time_limit(0);
        ob_implicit_flush();
    }



    /**
     * Sytem_Daemon::start()
     * Public method: spawn daemon process.
     *
     * @return void
     */
    public function start()
    {
        // initialize & check variables
        $this->_daemonInit();

        // become daemon
        $this->_daemonBecome();

    }

    /**
     * Sytem_Daemon::stop()
     * Public method: stop daemon process.
     *
     * @return void
     */
    public function stop()
    {
        $this->_logger(1, "stopping ".$this->app_name." daemon", 
            __FILE__, __CLASS__, __FUNCTION__, __LINE__);
        $this->_daemonDie();
    }


    /**
     * Sytem_Daemon::daemonSigHandler()
     * Public method: signal handler function
     *
     * @param integer $signo The posix signal received.
     * 
     * @return void
     */
    public function daemonSigHandler( $signo )
    {
        // Must be public or else will throw error: 
        // Fatal error: Call to private method 
        // Daemon::daemonSigHandler() from context '' 
        $this->_logger(0, $this->app_name." daemon received signal: ".$signo, 
            __FILE__, __CLASS__, __FUNCTION__, __LINE__);
        switch ($signo) {
        case SIGTERM:
            // handle shutdown tasks
            if ($this->_is_child) {
                $this->_daemonDie();
            } else {
                exit;
            }
            break;
        case SIGHUP:
            // handle restart tasks
            $this->_logger(1, $this->app_name." daemon received signal: restart", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            break;
        case SIGCHLD:
            $this->_logger(1, $this->app_name." daemon received signal: hold", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            while (pcntl_wait($status, WNOHANG OR WUNTRACED) > 0) {
                usleep(1000);
            }
            break;
        default:
            // handle all other signals
            break;
        }
    }


    
    /**
     * Sytem_Daemon::determineOS()
     * Returns an array(main, distro, version) of the OS it's executed on
     *
     * @return array
     */
    public function determineOS()
    {
        if (!isset($this->_fnc_cache[__FUNCTION__])) {
            $osv_files = array(
                "RedHat"=>"/etc/redhat-release",
                "SuSE"=>"/etc/SuSE-release",
                "Mandrake"=>"/etc/mandrake-release",
                "Debian"=>"/etc/debian_version",
                "Ubuntu"=>"/etc/lsb-release"
            );

            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $main   = "Windows";
                $distro = PHP_OS;
            } else {
                $main = php_uname('s');
                foreach ($osv_files as $distro=>$osv_file) {
                    if (file_exists($osv_file)) {
                        $version = trim(file_get_contents($osv_file));
                        break;
                    }
                }
            }

            $this->_fnc_cache[__FUNCTION__] = compact("main", "distro", "version");
        }

        return $this->_fnc_cache[__FUNCTION__];
    }    
    
    /**
     * Sytem_Daemon::initdWrite()
     * Public method: writes an: 'init.d' script on the filesystem
     *
     * @return boolean
     */
    public function initdWrite()
    {
        $initdFilepath = $this->initdFilepath();
        if (!$initdFilepath) {
            return false;
        }
        
        $initd = $this->initd();
        if (!$initd) {
            return false;
        }
        
        if (!file_exists(($initdFilepath))) {
            if (!file_put_contents($initdFilepath, $initd)) {
                return false;
            }
            
            if (!chmod($initdFilepath, 0777)) {
                return false;
            }
            return true;
        }
        return false;
    }
    
    /**
     * Sytem_Daemon::initdFilepath()
     * Returns an: 'init.d' script path as a string. for now only debian & ubuntu
     *
     * @return string
     */
    public function initdFilepath()
    {
        
        $initdFilepath = false;
        
        // collect OS information
        list($main, $distro, $version) = array_values($this->determineOS());
        
        // where to collect the skeleton (template) for our init.d script
        switch (strtolower($distro)){
        case "debian":
        case "ubuntu":
            // here it is for debian systems
            $initdFilepath = "/etc/init.d/".$this->app_name;
            break;
        default:
            // not supported yet
            $this->_logger(2, "skeleton retrieval for OS: ".$distro.
                " currently not supported ", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            break;
        }
        
        return $initdFilepath;
    }
    
    /**
     * Sytem_Daemon::initd()
     * Returns an: 'init.d' script as a string. for now only debian & ubuntu
     *
     * @return string
     */
    public function initd()
    {
        // initialize & check variables
        $this->_daemonInit();

        // sanity
        $daemon_filepath = $this->app_dir."/".$this->app_executable;
        if (!file_exists($daemon_filepath)) {
            $this->_logger(3, "unable to forge skeleton for non existing ".
                "daemon_filepath: ".$daemon_filepath.", try setting a valid ".
                "app_dir or app_executable", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }
        if (!$this->author_name) {
            $this->_logger(3, "unable to forge skeleton for non existing ".
                "author_name: ".$this->author_name."", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }
        if (!$this->author_email) {
            $this->_logger(3, "unable to forge skeleton for non existing ".
                "author_email: ".$this->author_email."", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }
        if (!$this->app_description) {
            $this->_logger(3, "unable to forge skeleton for non existing ".
                "app_description: ".$this->app_description."", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }

        // collect OS information
        list($main, $distro, $version) = array_values($this->determineOS());

        // where to collect the skeleton (template) for our init.d script
        switch (strtolower($distro)){
        case "debian":
        case "ubuntu":
            // here it is for debian systems
            $skeleton_filepath = "/etc/init.d/skeleton";
            break;
        default:
            // not supported yet
            $this->_logger(2, "skeleton retrieval for OS: ".$distro.
                " currently not supported ", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;
            break;
        }

        // open skeleton
        if (!file_exists($skeleton_filepath)) {
            $this->_logger(2, "skeleton file for OS: ".$distro." not found at: ".
                $skeleton_filepath, 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;
        } elseif ($skeleton = file_get_contents($skeleton_filepath)) {
            // skeleton opened, set replace vars
            switch (strtolower($distro)){
            default:
                $replace = array(
                    "Foo Bar" => $this->author_name,
                    "foobar@baz.org" => $this->author_email,
                    "daemonexecutablename" => $this->app_name,
                    "Example" => $this->app_name,
                    "skeleton" => $this->app_name,
                    "/usr/sbin/\$NAME" => $daemon_filepath,
                    "Description of the service"=> $this->app_description,
                    " --name \$NAME" => "",
                    "--options args" => "",
                    "# Please remove the \"Author\" ".
                        "lines above and replace them" => "",
                    "# with your own name if you copy and modify this script." => ""
                );
                break;
            }

            // replace skeleton placeholders with actual daemon information
            $skeleton = str_replace(array_keys($replace), 
                array_values($replace), 
                $skeleton);

            // return the forged init.d script as a string
            return $skeleton;
        }
    }




    /**
     * Sytem_Daemon::_daemonInit()
     * Put the running script in background
     *
     * @return boolean
     */
    private function _daemonInit() 
    {
        if ($this->_is_initialized) {
            return true;
        }

        $this->_is_initialized = true;

        if (!$this->_strisunix($this->app_name)) {
            $safe_name = $this->_strtounix($this->app_name);
            $this->_logger(4, "'".$this->app_name."' is not a valid daemon name, ".
                "try using '".$safe_name."' instead", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;
        } else {
            $this->_logger(1, "starting ".$this->app_name." daemon", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
        }
        if (!$this->pid_filepath) {
            $this->pid_filepath = "/var/run/".$this->app_name.".pid";
        }
        if (!$this->log_filepath) {
            $this->log_filepath = "/var/log/".$this->app_name."_daemon.log";
        }
        
        $this->_pid      = 0;
        $this->_is_child = false;
        if (!is_numeric($this->uid)) {
            $this->_logger(4, "".$this->app_name." daemon has invalid uid: ".
                $this->uid."", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }
        if (!is_numeric($this->gid)) {
            $this->_logger(4, "".$this->app_name." daemon has invalid gid: ".
                $this->gid."", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }
        if (!$this->app_dir) {
            $this->app_dir = dirname(__FILE__);
        }
        if (!$this->app_executable) {
            $this->app_executable = basename($_SERVER["SCRIPT_FILENAME"]);
        }

        if (!is_dir($this->app_dir)) {
            $this->_logger(4, "".$this->app_name." daemon has invalid app_dir: ".
                $this->app_dir."", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }
        
        return true;
    }

    /**
     * Sytem_Daemon::_daemonBecome()
     * Put the running script in background
     *
     * @return void
     */
    private function _daemonBecome() 
    {

        // important for daemons
        // see http://nl2.php.net/manual/en/function.pcntl-signal.php
        declare(ticks = 1);

        // setup signal handlers
        pcntl_signal(SIGCONT, array($this, "daemonSigHandler"));
        pcntl_signal(SIGALRM, array($this, "daemonSigHandler"));
        pcntl_signal(SIGINT, array($this, "daemonSigHandler"));
        pcntl_signal(SIGABRT, array($this, "daemonSigHandler"));
        
        pcntl_signal(SIGTERM, array($this, "daemonSigHandler"));
        pcntl_signal(SIGHUP, array($this, "daemonSigHandler"));
        pcntl_signal(SIGUSR1, array($this, "daemonSigHandler"));
        pcntl_signal(SIGCHLD, array($this, "daemonSigHandler"));

        // allowed?
        if ($this->_daemonIsRunning()) {
            $this->_logger(4, "".$this->app_name." daemon is still running. ".
                "exiting", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
        }

        // fork us!
        if (!$this->_daemonFork()) {
            $this->_logger(4, "".$this->app_name." daemon was unable to fork", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
        }

        // assume identity
        if (!posix_setuid($this->uid) || !posix_setgid($this->gid)) {
            $lvl = ($this->die_on_identitycrisis ? 4 : 3);
            $this->_logger($lvl, "".$this->app_name." daemon was unable assume ".
                "identity (uid=".$this->uid.", gid=".$this->gid.")", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
        }

        // additional PID succeeded check
        if (!is_numeric($this->_pid) || $this->_pid < 1) {
            $this->_logger(4, "".$this->app_name." daemon didn't have a valid ".
                "pid: '".$this->_pid."'", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
        } else {
            if (!file_put_contents($this->pid_filepath, $this->_pid)) {
                $this->_logger(4, "".$this->app_name." daemon was unable to write ".
                    "to pidfile: ".$this->pid_filepath."", 
                    __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            }
        }

        // change dir & umask
        @chdir($this->app_dir);
        @umask(0);
    }

    /**
     * Sytem_Daemon::_daemonIsRunning()
     * Check if a previous process with same pidfile was already running
     *
     * @return boolean
     */
    private function _daemonIsRunning() 
    {
        if(!file_exists($this->pid_filepath)) return false;
        $_pid = @file_get_contents($this->pid_filepath);

        if ($_pid !== false) {
            if (!posix_kill(intval($_pid), 0)) {
                // not responding so unlink pidfile
                @unlink($this->pid_filepath);
                $this->_logger(2, "".$this->app_name." daemon orphaned pidfile ".
                    "found and removed: ".$this->pid_filepath, 
                    __FILE__, __CLASS__, __FUNCTION__, __LINE__);
                return false;
            } else {
                return true;
            }
        } else {
            return false;
        }
    }

    /**
     * Sytem_Daemon::_daemonFork()
     * Fork process and kill parent process, the heart of the 'daemonization'
     *
     * @return boolean
     */
    private function _daemonFork()
    {
        $this->_logger(0, "forking ".$this->app_name." daemon", 
            __FILE__, __CLASS__, __FUNCTION__, __LINE__);

        $_pid = pcntl_fork();
        if ( $_pid == -1 ) {
            // error
            $this->_logger(3, "".$this->app_name." daemon could not be forked", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;
        } else if ($_pid) {
             // parent
            $this->_logger(0, "ending ".$this->app_name." parent process", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            exit();
        } else {
            // children
            $this->_is_child = true;
            $this->is_dying  = false;
            $this->_pid      = posix_getpid();
            return true;
        }
    }

    /**
     * Sytem_Daemon::_daemonWhatIAm()
     * Private method: return what the current process is: child or parent
     *
     * @return string
     */
    private function _daemonWhatIAm()
    {
        return ($this->_is_child?"child":"parent");
    }

    /**
     * Sytem_Daemon::_daemonDie()
     * Private method: kill the daemon
     *
     * @return void
     */
    private function _daemonDie()
    {
        if ($this->is_dying != true) {
            $this->is_dying = true;
            if ($this->_is_child && file_exists($this->pid_filepath)) {
                @unlink($this->pid_filepath);
            }
            exit();
        }
    }




    /**
     * Sytem_Daemon::_strisunix()
     * Check if a string has a unix proof format (stripped spaces, 
     * special chars, etc)
     *
     * @param string $str What string to test for unix compliance
     * 
     * @return boolean
     */
    
    private function _strisunix( $str )
    {
        return preg_match('/^[a-z0-9_]+$/', $str);
    }

    /**
     * Sytem_Daemon::_strtounix()
     * Convert a string to a unix proof format (strip spaces, 
     * special chars, etc)
     * 
     * @param string $str What string to make unix compliant
     * 
     * @return string
     */
    private function _strtounix( $str )
    {
        return preg_replace('/[^0-9a-z_]/', '', strtolower($str));
    }

    /**
     * Sytem_Daemon::_logger()
     * Log a string according to error levels specified in array: 
     * log_levels (4 is fatal)
     *
     * @param integer $level    What function the log record is from
     * @param string  $str      The log record
     * @param string  $file     What code file the log record is from
     * @param string  $class    What class the log record is from
     * @param string  $function What function the log record is from
     * @param integer $line     What code line the log record is from
     *  
     * @return void
     * @see _log_levels
     * @see log_filepath
     */
    private function _logger($level, $str, $file = false, $class = false, 
        $function = false, $line = false)
    {
        if ( $file == false || $class == false || 
            $function == false || $line == false ) {
            // saves resources if arguments are passed.
            // but by using debug_backtrace() it still works 
            // if someone forgets to pass them
            $dbg_bt   = @debug_backtrace();
            $class    = (isset($dbg_bt[1]["class"])?$dbg_bt[1]["class"]:"");
            $function = (isset($dbg_bt[1]["function"])?$dbg_bt[1]["function"]:"");
            $file     = $dbg_bt[0]["file"];
            $line     = $dbg_bt[0]["line"];
        }

        $str_pid   = "from[".$this->_daemonWhatIAm()."".posix_getpid()."] ";
        $str_level = $this->_log_levels[$level];
        $log_line  = str_pad($str_level."", 8, " ", STR_PAD_LEFT)." " .
            $str_pid." : ".$str; 
        //echo $log_line."\n";
        file_put_contents($this->log_filepath, $log_line."\n", FILE_APPEND);
        
        if ($level == 4) {
            // to run as a part of pear
            if ($this->pear) {            
                throw new PEAR_Exception($log_line);
            }
            $this->_daemonDie();
        }
    }
}


/**
 * An exception thrown by System_Daemon when it encounters an unrecoverable error.
 *
 * @category  System
 * @package   System_Daemon
 * @author    Kevin van Zonneveld <kevin@vanzonneveld.net>
 * @copyright 2008 Kevin van Zonneveld (http://kevin.vanzonneveld.net)
 * @license   http://www.opensource.org/licenses/bsd-license.php New BSD Licence
 * @version   SVN: Release: $Id$
 * @link      http://trac.plutonia.nl/projects/system_daemon
 * * 
 */
class System_Daemon_Exception extends PEAR_Exception
{

}//end class

?>