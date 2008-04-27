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

error_reporting(E_ALL);
spl_autoload_register(array('System_Daemon', 'autoload'));

/**
 * System_Daemon. Create daemons with practicle functions 
 * like $daemon->start()
 *
 * Requires PHP build with --enable-cli --with-pcntl.
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
    /**
     * Wether or not to run this class standalone, or as a part of PEAR
     *
     * @var boolean
     */
    public $pear = true;
    
    /**
     * Author name, e.g.: Kevin van zonneveld 
     * Required for forging init.d script
     *
     * @var string
     */
    public $authorName;

    /**
     * Author name, e.g.: kevin@vanzonneveld.net 
     * Required for forging init.d script
     *
     * @var string
     */
    public $authorEmail;
  
    /**
     * The application name, e.g.: logparser
     *
     * @var string
     */
    public $appName;

    /**
     * Daemon description, e.g.: Parses logfiles of vsftpd and stores them in MySQL
     * Required for forging init.d script
     *
     * @var string
     */
    public $appDescription;

    /**
     * The home directory of the applications, e.g.: /usr/local/logparser
     * Defaults to: SCRIPT_NAME dir
     * Highly recommended to set this yourself though
     *
     * @var string
     */
    public $appDir;

    /**
     * The executeble daemon file, e.g.: logparser.php
     * Defaults to: SCRIPT_NAME basename
     * Recommended to set this yourself though
     *
     * @var string
     */
    public $appExecutable;

    /**
     * The pid filepath , e.g.: /var/run/logparser.pid
     * Defaults to: /var/run/${appName}.pid
     *
     * @var string
     */
    public $appPidLocation;

    /**
     * The log filepath , e.g.: /var/log/logparser_daemon.log
     * Defaults to: /var/log/${appName}_daemon.log
     *
     * @var string
     */
    public $logLocation;

    /**
     * The user id under which to run the process, e.g.: 1000
     * Defaults to: 0, root (warning, very insecure!)
     * 
     * @var string
     */
    public $appRunAsUID = 0;

    /**
     * The group id under which to run the process, e.g.: 1000
     * Defaults to: 0, root (warning, very insecure!)
     *
     * @var string
     */
    public $appRunAsGID = 0;
    
    /**
     * Kill daemon if it cannot assume the identity (uid + gid)
     * that you specified.
     * Defaults to: true
     *
     * @var string
     */
    public $appDieOnIdentityCrisis = true;

    /**
     * Messages below this log level are ignored (not written 
     * to logfile, not displayed on screen) 
     * Defaults to: 1, info. Meaning info & higher are logged.
     *
     * @var integer
     */
    
    public $logVerbosity = 1;
    
    
    
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
     * The current process identifier
     *
     * @var integer
     */
    private $_processId = 0;

    /**
     * Wether the our daemon is being killed
     *
     * @var boolean
     */
    private $_daemonIsDying = false;    
    
    /**
     * Wether all the variables have been initialized
     *
     * @var boolean
     */
    private $_daemonIsInitialized = false;

    /**
     * Wether the current process is a forked child
     *
     * @var boolean
     */
    private $_processIsChild = false;
    
    /**
     * Cache that holds values of some functions 
     * for performance gain. Easier then doing 
     * if (!isset($this->XXX)) { $this->XXX = $this->XXX(); }
     * every time, in my opinion. 
     *
     * @var array
     */
    private $_intFunctionCache = array();


    
    /**
     * Constructs a System_Daemon object.
     *
     * @param string  $appName The unix name of your daemon application.
     * @param boolean $pear    Wether or not to run as a part of pear.
     *
     * @see start()
     * @see _daemonInit()
     * @see autoload()
     */
    public function __construct($appName, $pear = true)
    {
        $this->appName = $appName;
        $this->pear    = $pear;
        
        // to run as a part of PEAR
        if ( $this->pear ) {
            include_once "PEAR.php";
            include_once "PEAR/Exception.php";
            
            if (class_exists('System_Daemon_Exception', true) === false) {
                throw new Exception('Class System_Daemon_Exception not found');
            }            
        }
        
        // check the PHP configuration
        if (!defined("SIGHUP")) {
            trigger_error("PHP is compiled without --enable-pcntl directive\n", 
                E_USER_ERROR);
        }        
        
        // check for CLI
        if ((php_sapi_name() != 'cli')) {
            trigger_error("You can only create daemon from the command line\n", 
                E_USER_ERROR);
        }
        
    }//end __construct()

    /**
     * Autoload static method for loading classes and interfaces.
     * Code from the PHP_CodeSniffer package by Greg Sherwood and 
     * Marc McIntyre
     *
     * @param string $className The name of the class or interface.
     *
     * @return void
     */
    public static function autoload($className)
    {
        $parent     = 'System_';
        $parent_len = strlen($parent);
        if (substr($className, 0, $parent_len) == $parent) {
            $newClassName = substr($className, $parent_len);
        } else {
            $newClassName = $className;
        }

        $path = str_replace('_', '/', $newClassName).'.php';

        if (is_file(dirname(__FILE__).'/'.$path) === true) {
            // Check standard file locations based on class name.
            include dirname(__FILE__).'/'.$path;
        } else {
            // Everything else.
            include $path;
        }

    }//end autoload()
    
    /**
     * Spawn daemon process.
     *
     * @return void
     * @see stop()
     * @see _daemonInit()
     * @see _daemonBecome()
     */
    public function start()
    {
        // initialize & check variables
        $this->_daemonInit();
        
        //$this->log(4, "test");
        
        // become daemon
        $this->_daemonBecome();

    }//end start()

    /**
     * Stop daemon process.
     *
     * @return void
     * @see start()
     */
    public function stop()
    {
        $this->log(1, "stopping ".$this->appName." daemon", 
            __FILE__, __CLASS__, __FUNCTION__, __LINE__);
        $this->_daemonDie();
    }//end stop()
    
    /**
     * Almost every deamon requires a log file, this function can
     * facilitate that. Also handles class-generated errors, chooses 
     * either PEAR handling or PEAR-independant handling, depending on:
     * $this->pear
     * 
     * It logs a string according to error levels specified in array: 
     * $this->_logLevels (4 is fatal and handles daemon's death)
     *
     * @param integer $level    What function the log record is from
     * @param string  $str      The log record
     * @param string  $file     What code file the log record is from
     * @param string  $class    What class the log record is from
     * @param string  $function What function the log record is from
     * @param integer $line     What code line the log record is from
     *
     * @throws System_Daemon_Exception  
     * @return boolean
     * @see _logLevels
     * @see logLocation
     */
    public function log($level, $str, $file = false, $class = false, 
        $function = false, $line = false)
    {
        if ($level < $this->logVerbosity) {
            return true;
        }
        
        
        if ( function_exists("debug_backtrace") && ($file == false || 
            $class == false || $function == false || $line == false) ) {
            // saves resources if arguments are passed.
            // but by using debug_backtrace() it still works 
            // if someone forgets to pass them
            $dbg_bt   = @debug_backtrace();
            $class    = (isset($dbg_bt[1]["class"])?$dbg_bt[1]["class"]:"");
            $function = (isset($dbg_bt[1]["function"])?$dbg_bt[1]["function"]:"");
            $file     = $dbg_bt[0]["file"];
            $line     = $dbg_bt[0]["line"];
        }

        // determine what process the log is originating from and forge a logline
        $str_date  = "[".date("M d H:i:s")."]"; 
        $str_ident = "@".substr($this->_daemonWhatIAm(), 0, 1)."-".posix_getpid();
        $str_level = str_pad($this->_logLevels[$level]."", 8, " ", STR_PAD_LEFT);
        $log_line  = $str_date." ".$str_level.": ".$str; // $str_ident
        
        if ($level > 0) {
            if (!$this->daemonInBackground() || !is_writable($this->logLocation)) {
                // it's okay to echo if you're running as a fore-ground process
                // maybe the command to write an init.d file was issued.
                // in such a case it's important to echo failures to the 
                // STDOUT
                echo $log_line."\n";
            }
            
            // write to logfile
            file_put_contents($this->logLocation, $log_line."\n", FILE_APPEND);
        }
        
        if ($level > 1) {
            if ($this->pear) {
                PEAR::raiseError($log_line);
            }
        }
        
        if ($level == 4) {
            // to run as a part of pear
            if ($this->pear) {            
                throw new System_Daemon_Exception($log_line);
            }
            $this->_daemonDie();
        }
        
        return true;
        
    }//end log()    
    
    /**
     * Signal handler function
     *
     * @param integer $signo The posix signal received.
     * 
     * @return void
     */
    public function daemonHandleSig( $signo )
    {
        // Must be public or else will throw error: 
        // Fatal error: Call to private method 
        // Daemon::daemonHandleSig() from context '' 
        $this->log(0, $this->appName." daemon received signal: ".$signo, 
            __FILE__, __CLASS__, __FUNCTION__, __LINE__);
        switch ($signo) {
        case SIGTERM:
            // handle shutdown tasks
            if ($this->daemonInBackGround()) {
                $this->_daemonDie();
            } else {
                exit;
            }
            break;
        case SIGHUP:
            // handle restart tasks
            $this->log(1, $this->appName." daemon received signal: restart", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            break;
        case SIGCHLD:
            $this->log(1, $this->appName." daemon received signal: hold", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            while (pcntl_wait($status, WNOHANG OR WUNTRACED) > 0) {
                usleep(1000);
            }
            break;
        default:
            // handle all other signals
            break;
        }
    }//end daemonHandleSig()

    /**
     * Wether the class is already running in the background
     * 
     * @return boolean
     */
    public function daemonInBackground()
    {
        return $this->_processIsChild;
    }//end daemonInBackground()
    
    /**
     * Wether the our daemon is being killed, you might 
     * want to include this in your loop
     * 
     * @return boolean
     */
    public function daemonIsDying()
    {
        return $this->_daemonIsDying;
    }//end daemonIsDying()
    
    /**
     * Initializes, sanitizes & defaults unset variables
     *
     * @return boolean
     */
    private function _daemonInit() 
    {
        // if already initialized, skip
        if ($this->_daemonIsInitialized) {
            return true;
        }
        
        // system settings
        $this->_processId      = 0;
        $this->_processIsChild = false;
        ini_set("max_execution_time", "0");
        ini_set("max_input_time", "0");
        ini_set("memory_limit", "1024M");
        set_time_limit(0);        
        ob_implicit_flush();
        
        // verify appName
        if (!$this->_strIsUnix($this->appName)) {
            // suggest a better appName
            $safe_name = $this->_strToUnix($this->appName);
            $this->log(4, "'".$this->appName."' is not a valid daemon name, ".
                "try using something like '".$safe_name."' instead", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }
        
        // default appPidLocation
        if (!$this->appPidLocation) {
            $this->appPidLocation = "/var/run/".$this->appName.".pid";
        }
        // verify appPidLocation
        if (!is_writable($dir = dirname($this->appPidLocation))) {
            $this->log(4, "".$this->appName." daemon cannot write to ".
                "pidfile directory: ".$dir, 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }

        // default logLocation
        if (!$this->logLocation) {
            $this->logLocation = "/var/log/".$this->appName.".log";
        }
        // verify logLocation
        if (!is_writable($dir = dirname($this->logLocation))) {
            $this->log(4, "".$this->appName." daemon cannot write ".
                "to log directory: ".$dir,
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }
        
        // verify logVerbosity
        if ($this->logVerbosity < 0 || $this->logVerbosity > 4) {
            $this->log(4, "logVerbosity needs to be between 0 and 4 ".
                "logVerbosity: ".$this->logVerbosity."", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }
        
        // verify appRunAsUID
        if (!is_numeric($this->appRunAsUID)) {
            $this->log(4, "".$this->appName." daemon has invalid ".
                "appRunAsUID: ".$this->appRunAsUID.". ",
                "It should be an integer", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }
        $passwd = posix_getpwuid($this->appRunAsUID);
        if (!is_array($passwd) || !count($passwd) || 
            !isset($passwd["name"]) || !$passwd["name"]) {
            $this->log(4, "".$this->appName." daemon has invalid ".
                "appRunAsUID: ".$this->appRunAsUID.". ".
                "No matching user on the system. ", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;            
        }

        // verify appRunAsGID
        if (!is_numeric($this->appRunAsGID)) {
            $this->log(4, "".$this->appName." daemon has invalid ".
                "appRunAsGID: ".$this->appRunAsGID.". ",
                "It should be an integer",  
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }
        $group = posix_getgrgid($this->appRunAsGID);
        if (!is_array($group) || !count($group) || 
            !isset($group["name"]) || !$group["name"]) {
            $this->log(4, "".$this->appName." daemon has invalid ".
                "appRunAsGID: ".$this->appRunAsGID.". ".
                "No matching group on the system. ", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;            
        }
        
        // default appDir
        if (!$this->appDir) {
            $this->appDir = dirname($_SERVER["SCRIPT_FILENAME"]);
        }
        // verify appDir
        if (!is_dir($this->appDir)) {
            $this->log(4, "".$this->appName." daemon has invalid appDir: ".
                $this->appDir."", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }
        
        // verify appExecutable
        if (!$this->appExecutable) {
            $this->appExecutable = basename($_SERVER["SCRIPT_FILENAME"]);
        }

        
        // combine appdir + exe here to make SURE we got our data right 
        
        $this->_daemonIsInitialized = true;
        return true;
    }//end _daemonInit()

    /**
     * Put the running script in background
     *
     * @return void
     */
    private function _daemonBecome() 
    {

        $this->log(1, "starting ".$this->appName." daemon, output in: ". 
            $this->logLocation, 
            __FILE__, __CLASS__, __FUNCTION__, __LINE__);
        
        // important for daemons
        // see http://nl2.php.net/manual/en/function.pcntl-signal.php
        declare(ticks = 1);

        // setup signal handlers
        pcntl_signal(SIGCONT, array($this, "daemonHandleSig"));
        pcntl_signal(SIGALRM, array($this, "daemonHandleSig"));
        pcntl_signal(SIGINT, array($this, "daemonHandleSig"));
        pcntl_signal(SIGABRT, array($this, "daemonHandleSig"));
        
        pcntl_signal(SIGTERM, array($this, "daemonHandleSig"));
        pcntl_signal(SIGHUP, array($this, "daemonHandleSig"));
        pcntl_signal(SIGUSR1, array($this, "daemonHandleSig"));
        pcntl_signal(SIGCHLD, array($this, "daemonHandleSig"));

        // allowed?
        if ($this->_daemonIsRunning()) {
            $this->log(4, "".$this->appName." daemon is still running. ".
                "exiting", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
        }

        // fork process!
        if (!$this->_daemonFork()) {
            $this->log(4, "".$this->appName." daemon was unable to fork", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
        }

        // assume specified identity (uid & gid)
        if (!posix_setuid($this->appRunAsUID) || 
            !posix_setgid($this->appRunAsGID)) {
            if ($this->appDieOnIdentityCrisis) {
                $lvl = 4;
                $swt = "on";
            } else {
                $lvl = 3;
                $swt = "off";
            }
            $this->log($lvl, "".$this->appName." daemon was unable assume ".
                "identity (uid=".$this->appRunAsUID.", gid=".
                $this->appRunAsGID.") ".
                "and appDieOnIdentityCrisis was ". $swt, 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
        }

        // additional PID succeeded check
        if (!is_numeric($this->_processId) || $this->_processId < 1) {
            $this->log(4, "".$this->appName." daemon didn't have a valid ".
                "pid: '".$this->_processId."'", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
        } else {
            if (!file_put_contents($this->appPidLocation, $this->_processId)) {
                $this->log(4, "".$this->appName." daemon was unable ".
                    "to write to pidfile: ".$this->appPidLocation."", 
                    __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            }
        }

        // change dir & umask
        @chdir($this->appDir);
        @umask(0);
    }//end _daemonBecome()

    /**
     * Check if a previous process with same pidfile was already running
     *
     * @return boolean
     */
    private function _daemonIsRunning() 
    {
        if(!file_exists($this->appPidLocation)) return false;
        $pid = @file_get_contents($this->appPidLocation);

        if ($pid !== false) {
            if (!posix_kill(intval($pid), 0)) {
                // not responding so unlink pidfile
                @unlink($this->appPidLocation);
                $this->log(2, "".$this->appName." daemon orphaned pidfile ".
                    "found and removed: ".$this->appPidLocation, 
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
     * Fork process and kill parent process, the heart of the 'daemonization'
     *
     * @return boolean
     */
    private function _daemonFork()
    {
        $this->log(0, "forking ".$this->appName." daemon", 
            __FILE__, __CLASS__, __FUNCTION__, __LINE__);

        $pid = pcntl_fork();
        if ( $pid == -1 ) {
            // error
            $this->log(3, "".$this->appName." daemon could not be forked", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;
        } else if ($pid) {
            // parent
            $this->log(0, "ending ".$this->appName." parent process", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            // die without attracting attention
            exit();
        } else {
            // child
            $this->_processIsChild = true;
            $this->_daemonIsDying  = false;
            $this->_processId      = posix_getpid();
            return true;
        }
    }//end _daemonFork()

    /**
     * Return what the current process is: child or parent
     *
     * @return string
     */
    private function _daemonWhatIAm()
    {
        return ($this->daemonInBackground()?"child":"parent");
    }//end _daemonWhatIAm()

    /**
     * Sytem_Daemon::_daemonDie()
     * Kill the daemon
     *
     * @return void
     */
    private function _daemonDie()
    {
        if (!$this->daemonIsDying()) {
            $this->_daemonIsDying       = true;
            $this->_daemonIsInitialized = false;
            if (!$this->daemonInBackground() || 
                !file_exists($this->appPidLocation)) {
                $this->log(1, "Not stopping ".$this->appName.
                    ", daemon was not running",
                    __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            } else {
                @unlink($this->appPidLocation);
            }
            exit();
        }
    }//end _daemonDie()

    
    
    /**
     * Check if a string has a unix proof format (stripped spaces, 
     * special chars, etc)
     *
     * @param string $str What string to test for unix compliance
     * 
     * @return boolean
     */   
    private function _strIsUnix( $str )
    {
        return preg_match('/^[a-z0-9_]+$/', $str);
    }//end _strIsUnix()

    /**
     * Convert a string to a unix proof format (strip spaces, 
     * special chars, etc)
     * 
     * @param string $str What string to make unix compliant
     * 
     * @return string
     */
    private function _strToUnix( $str )
    {
        return preg_replace('/[^0-9a-z_]/', '', strtolower($str));
    }//end _strToUnix()


}//end class
?>