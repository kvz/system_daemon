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
    public $appName;

    /**
     * The home dirpath e.g.: /usr/local/logparser. Defaults to __FILE__ dir
     *
     * @var string
     */
    public $appDir;

    /**
     * The executeble daemon file, e.g.: logparser.php. 
     * Defaults to: SCRIPT_NAME basename
     *
     * @var string
     */
    public $appExecutable;

    /**
     * Daemon description. Required for forging init.d script
     *
     * @var string
     */
    public $appDescription;

    /**
     * Author name. Required for forging init.d script
     *
     * @var string
     */
    public $authorName;

    /**
     * Author email. Required for forging init.d script
     *
     * @var string
     */
    public $authorEmail;
  
    /**
     * The pid filepath , e.g.: /var/run/logparser.pid. 
     * Defaults to: /var/run/${appName}.pid
     *
     * @var string
     */
    public $pid_filepath;

    /**
     * The log filepath , e.g.: /var/log/logparser_daemon.log. 
     * Defaults to: /var/log/${appName}_daemon.log
     *
     * @var string
     */
    public $logFilepath;

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
    public $isDying = false;
    
    /**
     * Kill daemon if it cannot assume the identity (uid + gid)
     *
     * @var string
     */
    public $dieOnIdentitycrisis = true;

    /**
     * Available log levels
     *
     * @var array
     */
    private $_logLevels = array(
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
    private $_isChild = false;

    /**
     * Wether all the variables have been initialized
     *
     * @var boolean
     */
    private $_isInitialized = false;

    /**
     * Cache that holds values of some functions 
     * for performance gain
     *
     * @var array
     */
    private $_fncCache = array();


    /**
     * Constructs a System_Daemon object.
     *
     * @param string  $appName The unix name of your daemon application.
     * @param boolean $pear    Wether or not to run as a part of pear.
     *
     * @see start()
     */
    public function __construct($appName, $pear = true)
    {
        $this->appName = $appName;
        $this->pear    = $pear;
        
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
    }//end __construct()



    /**
     * Sytem_Daemon::start()
     * Spawn daemon process.
     *
     * @return void
     */
    public function start()
    {
        // initialize & check variables
        $this->_daemonInit();

        // become daemon
        $this->_daemonBecome();

    }//end start()

    /**
     * Sytem_Daemon::stop()
     * Stop daemon process.
     *
     * @return void
     */
    public function stop()
    {
        $this->_logger(1, "stopping ".$this->appName." daemon", 
            __FILE__, __CLASS__, __FUNCTION__, __LINE__);
        $this->_daemonDie();
    }//end stop()


    /**
     * Sytem_Daemon::daemonSigHandler()
     * Signal handler function
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
        $this->_logger(0, $this->appName." daemon received signal: ".$signo, 
            __FILE__, __CLASS__, __FUNCTION__, __LINE__);
        switch ($signo) {
        case SIGTERM:
            // handle shutdown tasks
            if ($this->_isChild) {
                $this->_daemonDie();
            } else {
                exit;
            }
            break;
        case SIGHUP:
            // handle restart tasks
            $this->_logger(1, $this->appName." daemon received signal: restart", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            break;
        case SIGCHLD:
            $this->_logger(1, $this->appName." daemon received signal: hold", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            while (pcntl_wait($status, WNOHANG OR WUNTRACED) > 0) {
                usleep(1000);
            }
            break;
        default:
            // handle all other signals
            break;
        }
    }//end daemonSigHandler()


    
    /**
     * Sytem_Daemon::determineOS()
     * Returns an array(main, distro, version) of the OS it's executed on
     *
     * @return array
     */
    public function determineOS()
    {
        if (!isset($this->_fncCache[__FUNCTION__])) {
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

            $this->_fncCache[__FUNCTION__] = compact("main", "distro", "version");
        }

        return $this->_fncCache[__FUNCTION__];
    }//end determineOS()  
    
    /**
     * Sytem_Daemon::initdWrite()
     * Writes an: 'init.d' script on the filesystem
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
    }//end initdWrite() 
    
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
            $initdFilepath = "/etc/init.d/".$this->appName;
            break;
        default:
            // not supported yet
            $this->_logger(2, "skeleton retrieval for OS: ".$distro.
                " currently not supported ", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            break;
        }
        
        return $initdFilepath;
    }//end initdFilepath()
    
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
        $daemon_filepath = $this->appDir."/".$this->appExecutable;
        if (!file_exists($daemon_filepath)) {
            $this->_logger(3, "unable to forge skeleton for non existing ".
                "daemon_filepath: ".$daemon_filepath.", try setting a valid ".
                "appDir or appExecutable", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }
        if (!$this->authorName) {
            $this->_logger(3, "unable to forge skeleton for non existing ".
                "authorName: ".$this->authorName."", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }
        if (!$this->authorEmail) {
            $this->_logger(3, "unable to forge skeleton for non existing ".
                "authorEmail: ".$this->authorEmail."", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }
        if (!$this->appDescription) {
            $this->_logger(3, "unable to forge skeleton for non existing ".
                "appDescription: ".$this->appDescription."", 
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
                    "Foo Bar" => $this->authorName,
                    "foobar@baz.org" => $this->authorEmail,
                    "daemonexecutablename" => $this->appName,
                    "Example" => $this->appName,
                    "skeleton" => $this->appName,
                    "/usr/sbin/\$NAME" => $daemon_filepath,
                    "Description of the service"=> $this->appDescription,
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
    }//end initd()




    /**
     * Sytem_Daemon::_daemonInit()
     * Put the running script in background
     *
     * @return boolean
     */
    private function _daemonInit() 
    {
        if ($this->_isInitialized) {
            return true;
        }

        $this->_isInitialized = true;

        if (!$this->_strisunix($this->appName)) {
            $safe_name = $this->_strtounix($this->appName);
            $this->_logger(4, "'".$this->appName."' is not a valid daemon name, ".
                "try using '".$safe_name."' instead", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;
        } else {
            $this->_logger(1, "starting ".$this->appName." daemon", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
        }
        if (!$this->pid_filepath) {
            $this->pid_filepath = "/var/run/".$this->appName.".pid";
        }
        if (!$this->logFilepath) {
            $this->logFilepath = "/var/log/".$this->appName."_daemon.log";
        }
        
        $this->_pid     = 0;
        $this->_isChild = false;
        if (!is_numeric($this->uid)) {
            $this->_logger(4, "".$this->appName." daemon has invalid uid: ".
                $this->uid."", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }
        if (!is_numeric($this->gid)) {
            $this->_logger(4, "".$this->appName." daemon has invalid gid: ".
                $this->gid."", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }
        if (!$this->appDir) {
            $this->appDir = dirname(__FILE__);
        }
        if (!$this->appExecutable) {
            $this->appExecutable = basename($_SERVER["SCRIPT_FILENAME"]);
        }

        if (!is_dir($this->appDir)) {
            $this->_logger(4, "".$this->appName." daemon has invalid appDir: ".
                $this->appDir."", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }
        
        return true;
    }//end _daemonInit()

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
            $this->_logger(4, "".$this->appName." daemon is still running. ".
                "exiting", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
        }

        // fork us!
        if (!$this->_daemonFork()) {
            $this->_logger(4, "".$this->appName." daemon was unable to fork", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
        }

        // assume identity
        if (!posix_setuid($this->uid) || !posix_setgid($this->gid)) {
            $lvl = ($this->dieOnIdentitycrisis ? 4 : 3);
            $this->_logger($lvl, "".$this->appName." daemon was unable assume ".
                "identity (uid=".$this->uid.", gid=".$this->gid.")", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
        }

        // additional PID succeeded check
        if (!is_numeric($this->_pid) || $this->_pid < 1) {
            $this->_logger(4, "".$this->appName." daemon didn't have a valid ".
                "pid: '".$this->_pid."'", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
        } else {
            if (!file_put_contents($this->pid_filepath, $this->_pid)) {
                $this->_logger(4, "".$this->appName." daemon was unable to write ".
                    "to pidfile: ".$this->pid_filepath."", 
                    __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            }
        }

        // change dir & umask
        @chdir($this->appDir);
        @umask(0);
    }//end _daemonBecome()

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
                $this->_logger(2, "".$this->appName." daemon orphaned pidfile ".
                    "found and removed: ".$this->pid_filepath, 
                    __FILE__, __CLASS__, __FUNCTION__, __LINE__);
                return false;
            } else {
                return true;
            }
        } else {
            return false;
        }
    }//end _daemonIsRunning()

    /**
     * Sytem_Daemon::_daemonFork()
     * Fork process and kill parent process, the heart of the 'daemonization'
     *
     * @return boolean
     */
    private function _daemonFork()
    {
        $this->_logger(0, "forking ".$this->appName." daemon", 
            __FILE__, __CLASS__, __FUNCTION__, __LINE__);

        $_pid = pcntl_fork();
        if ( $_pid == -1 ) {
            // error
            $this->_logger(3, "".$this->appName." daemon could not be forked", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;
        } else if ($_pid) {
             // parent
            $this->_logger(0, "ending ".$this->appName." parent process", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            exit();
        } else {
            // children
            $this->_isChild = true;
            $this->isDying  = false;
            $this->_pid     = posix_getpid();
            return true;
        }
    }//end _daemonFork()

    /**
     * Sytem_Daemon::_daemonWhatIAm()
     * Return what the current process is: child or parent
     *
     * @return string
     */
    private function _daemonWhatIAm()
    {
        return ($this->_isChild?"child":"parent");
    }//end _()

    /**
     * Sytem_Daemon::_daemonDie()
     * Kill the daemon
     *
     * @return void
     */
    private function _daemonDie()
    {
        if ($this->isDying != true) {
            $this->isDying = true;
            if ($this->_isChild && file_exists($this->pid_filepath)) {
                @unlink($this->pid_filepath);
            }
            exit();
        }
    }//end _daemonDie()




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
    }//end _strisunix()

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
    }//end _strtounix()

    /**
     * Sytem_Daemon::_logger()
     * Almost every deamon requires a log file, this function can
     * facilitate that. 
     * It logs a string according to error levels specified in array: 
     * log_levels (4 is fatal and handles daemon's death)
     *
     * @param integer $level    What function the log record is from
     * @param string  $str      The log record
     * @param string  $file     What code file the log record is from
     * @param string  $class    What class the log record is from
     * @param string  $function What function the log record is from
     * @param integer $line     What code line the log record is from
     *  
     * @return void
     * @see _logLevels
     * @see logFilepath
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
        $str_level = $this->_logLevels[$level];
        $log_line  = str_pad($str_level."", 8, " ", STR_PAD_LEFT)." " .
            $str_pid." : ".$str; 
        //echo $log_line."\n";
        file_put_contents($this->logFilepath, $log_line."\n", FILE_APPEND);
        
        if ($level == 4) {
            // to run as a part of pear
            if ($this->pear) {            
                throw new PEAR_Exception($log_line);
            }
            $this->_daemonDie();
        }
    }//end _logger()
}//end class


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