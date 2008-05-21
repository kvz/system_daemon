<?php
/* vim: set noai expandtab tabstop=4 softtabstop=4 shiftwidth=4: */

/**
 * System_Daemon turns PHP-CLI scripts into daemons.
 * 
 * PHP version 5
 *
 * @category  System
 * @package   System_Daemon
 * @author    Kevin van Zonneveld <kevin@vanzonneveld.net>
 * @copyright 2008 Kevin van Zonneveld (http://kevin.vanzonneveld.net)
 * @license   http://www.opensource.org/licenses/bsd-license.php New BSD Licence
 * @version   SVN: Release: $Id$
 * @link      http://trac.plutonia.nl/projects/system_daemon
 */

// Autoloader borrowed from PHP_CodeSniffer, see function for credits
spl_autoload_register(array("System_Daemon", "autoload"));

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
 * @author    Kevin van Zonneveld <kevin@vanzonneveld.net>
 * @copyright 2008 Kevin van Zonneveld (http://kevin.vanzonneveld.net)
 * @license   http://www.opensource.org/licenses/bsd-license.php New BSD Licence
 * @version   SVN: Release: $Id$
 * @link      http://trac.plutonia.nl/projects/system_daemon
 * 
 */
class System_Daemon
{
    // Make these corresponding with PEAR
    // Ensures compatibility while maintaining independency
    
    /**
     * System is unusable
     */
    const LOG_EMERG = 0;
    
    /**
     * Immediate action required
     */ 
    const LOG_ALERT = 1;
    
    /**
     * Critical conditions
     */
    const LOG_CRIT = 2;
    
    /**
     * Error conditions
     */
    const LOG_ERR = 3;
    
    /**
     * Warning conditions
     */
    const LOG_WARNING = 4;
    
    /**
     * Normal but significant
     */
    const LOG_NOTICE = 5;
    
    /**
     * Informational
     */
    const LOG_INFO = 6;
    
    /**
     * Debug-level messages
     */
    const LOG_DEBUG = 7;
    
    
    
    /**
     * The current process identifier
     *
     * @var integer
     */
    static private $_processId = 0;

    /**
     * Wether the our daemon is being killed
     *
     * @var boolean
     */
    static private $_daemonIsDying = false;    
    
    /**
     * Wether the current process is a forked child
     *
     * @var boolean
     */
    static private $_processIsChild = false;
    
    /**
     * Wether SAFE_MODE is on or off. This is important for ini_set
     * behavior
     *
     * @var boolean
     */
    static private $_safeMode = false;
    
    /**
     * Available log levels
     *
     * @var array
     */
    static private $_logLevels = array(
        self::LOG_EMERG => "emerg",
        self::LOG_ALERT => "alert",
        self::LOG_CRIT => "crit",
        self::LOG_ERR => "err",
        self::LOG_WARNING => "warning",
        self::LOG_NOTICE => "notice",
        self::LOG_INFO => "info",
        self::LOG_DEBUG => "debug"        
    );
    
    /**
     * Holds Option Object
     * 
     * @var mixed object or boolean
     */
    static private $_optObj = false;
    
    /**
     * Definitions for all Options
     *
     * @var array
     */
    static private $_optionDefinitions = array(
        "usePEAR" => array(
            "type" => "boolean",
            "default" => true,
            "punch" => "Wether to run this class using PEAR",
            "detail" => "Will run standalone when false",
            "required" => true 
        ),
        "usePEARLogInstance" => array(
            "type" => "boolean|object",
            "default" => false,
            "punch" => "Accepts a PEAR_Log instance to handle all logging",
            "detail" => "This will replace System_Daemon's own logging facility",
            "required" => true
        ),
        
        "authorName" => array(
            "type" => "string/0-50",
            "punch" => "Author name",
            "example" => "Kevin van zonneveld",   
            "detail" => "Required for forging init.d script"
        ),
        "authorEmail" => array(
            "type" => "string/email",
            "punch" => "Author e-mail",
            "example" => "kevin@vanzonneveld.net",
            "detail" => "Required for forging init.d script"
        ),
        "appName" => array(
            "type" => "string/unix",
            "punch" => "The application name",
            "example" => "logparser",
            "detail" => "Must be UNIX-proof; Required for running daemon",
            "required" => true
        ),
        "appDescription" => array(
            "type" => "string",
            "punch" => "Daemon description",
            "example" => "Parses logfiles of vsftpd and stores them in MySQL",
            "detail" => "Required for forging init.d script"
        ),
        "appDir" => array(
            "type" => "string/existing_dirpath",
            "default" => "@dirname({SERVER.SCRIPT_NAME})",
            "punch" => "The home directory of the daemon",
            "example" => "/usr/local/logparser",
            "detail" => "Highly recommended to set this yourself",
            "required" => true
        ),
        "appExecutable" => array(
            "type" => "string/existing_filepath",
            "default" => "@basename({SERVER.SCRIPT_NAME})",
            "punch" => "The executable daemon file",
            "example" => "logparser.php",
            "detail" => "Recommended to set this yourself; Required for init.d",
            "required" => true
        ),
        
        "logVerbosity" => array(
            "type" => "number/0-7",
            "default" => self::LOG_INFO,
            "punch" => "Messages below this log level are ignored",
            "example" => "",
            "detail" => "Not written to logfile; not displayed on screen",
            "required" => true
        ),
        "logLocation" => array(
            "type" => "string/creatable_filepath",
            "default" => "/var/log/{OPTIONS.appName}.log",
            "punch" => "The log filepath",
            "example" => "/var/log/logparser_daemon.log",
            "detail" => "",
            "required" => true
        ),
        
        "appRunAsUID" => array(
            "type" => "number/0-65000",
            "default" => 0,
            "punch" => "The user id under which to run the process",
            "example" => "1000",
            "detail" => "Defaults to root which is insecure!",
            "required" => true
        ),
        "appRunAsGID" => array(
            "type" => "number/0-65000",
            "default" => 0,
            "punch" => "The group id under which to run the process",
            "example" => "1000",
            "detail" => "Defaults to root which is insecure!",
            "required" => true
        ),
        "appPidLocation" => array(
            "type" => "string/creatable_filepath",
            "default" => "/var/run/{OPTIONS.appName}.pid",
            "punch" => "The pid filepath",
            "example" => "/var/run/logparser.pid",
            "detail" => "",
            "required" => true
        ),
        "appDieOnIdentityCrisis" => array(
            "type" => "boolean",
            "default" => true,
            "punch" => "Kill daemon if it cannot assume the identity",
            "detail" => "",
            "required" => true
        ),

        "sysMaxExecutionTime" => array(
            "type" => "number",
            "default" => 0,
            "punch" => "Maximum execution time of each script in seconds",
            "detail" => "0 is infinite"
        ),
        "sysMaxInputTime" => array(
            "type" => "number",
            "default" => 0,
            "punch" => "Maximum time to spend parsing request data",
            "detail" => "0 is infinite"
        ),
        "sysMemoryLimit" => array(
            "type" => "string",
            "default" => "128M",
            "punch" => "Maximum amount of memory a script may consume",
            "detail" => "0 is infinite"
        ),        
    );
    
    /**
     * Available signal handlers
     * setSigHandler can overwrite these values individually.
     *
     * @var array
     * @see setSigHandler()
     */
    static private $_sigHandlers = array(
        SIGCONT => array("System_Daemon", "daemonHandleSig"),
        SIGALRM => array("System_Daemon", "daemonHandleSig"),
        SIGINT => array("System_Daemon", "daemonHandleSig"),
        SIGABRT => array("System_Daemon", "daemonHandleSig"),
        SIGTERM => array("System_Daemon", "daemonHandleSig"),
        SIGHUP => array("System_Daemon", "daemonHandleSig"),
        SIGUSR1 => array("System_Daemon", "daemonHandleSig"),
        SIGCHLD => array("System_Daemon", "daemonHandleSig")
    );

    /**
     * Cache that holds values of some functions 
     * for performance gain. Easier then doing 
     * if (!isset(self::$XXX)) { self::$XXX = self::XXX(); }
     * every time, in my opinion. 
     *
     * @var array
     */
    static private $_intFunctionCache = array();
    

    
    /**
     * Making the class non-abstract with a private constructor does a better
     * job of preventing instantiation than just marking the class as abstract.
     * 
     * @see start()
     */
    private function __construct() 
    {
        
    }    
    
    
    
    /**
     * Autoload static method for loading classes and interfaces.
     * Code from the PHP_CodeSniffer package by Greg Sherwood and 
     * Marc McIntyre
     *
     * @param string $className The name of the class or interface.
     *
     * @return void
     */
    static public function autoload($className)
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
            @include $path;
        }

    }//end autoload()
    
    
    /**
     * Spawn daemon process.
     * 
     * @return boolean
     * @see stop()
     * @see autoload()
     * @see _optionsInit()
     * @see _daemonBecome()
     */
    static public function start()
    {        
        
        // Quickly initialize some defaults like usePEAR 
        // by adding the $premature flag
        self::_optionsInit(true);
        
        // To run as a part of PEAR
        if (self::getOption("usePEAR")) {
            // SPL's autoload will make sure classes are automatically loaded
            if (class_exists('PEAR', true) === false) {
                $msg = "PEAR not found. Install PEAR or run with option: ".
                    "usePEAR = false";
                trigger_error($msg, E_USER_ERROR);                
            }
            
            if (class_exists('PEAR_Exception', true) === false) {
                $msg = "PEAR_Exception not found?!";
                trigger_error($msg, E_USER_ERROR);                
            }
                        
            if (class_exists('System_Daemon_Exception', true) === false) {
                // PEAR_Exception is OK. PEAR was found already.
                throw new PEAR_Exception('Class System_Daemon_Exception not found');
            }            
        }
        
        // Check the PHP configuration
        if (!defined("SIGHUP")) {
            $msg = "PHP is compiled without --enable-pcntl directive";
            if (self::getOption("usePEAR")) {
                throw new System_Daemon_Exception($msg);
            } else {
                trigger_error($msg, E_USER_ERROR);
            }
        }        
        
        // Check for CLI
        if ((php_sapi_name() != 'cli')) {
            $msg = "You can only create daemon from the command line";
            if (self::getOption("usePEAR")) {
                throw new System_Daemon_Exception($msg);
            } else {
                trigger_error($msg, E_USER_ERROR);
            }
        }
        
        // Initialize & check variables
        if (self::_optionsInit(false) === false) {
            if (is_object(self::$_optObj) && is_array(self::$_optObj->errors)) {
                foreach (self::$_optObj->errors as $error) {
                    self::log(self::LOG_NOTICE, $error);
                }
            }
            
            $msg = "Crucial options are not set. Review log:";
            if (self::getOption("usePEAR")) {
                throw new System_Daemon_Exception($msg);
            } else {
                trigger_error($msg, E_USER_ERROR);
            } 
        }
                
        // Become daemon
        self::_daemonBecome();
        
        return true;

    }//end start()
    
    /**
     * Stop daemon process.
     *
     * @return void
     * @see start()
     */
    static public function stop()
    {
        self::log(self::LOG_INFO, "stopping ".
            self::getOption("appName")." daemon", 
            __FILE__, __CLASS__, __FUNCTION__, __LINE__);
        self::_daemonDie();
    }//end stop()
    
    
    /**
     * Overrule or add signal handlers.
     *
     * @param string $signal  Signal constant (e.g. SIGHUP)
     * @param mixed  $handler Which handler to call on signal
     * 
     * @return boolean
     * @see $_sigHandlers
     */
    static public function setSigHandler($signal, $handler)
    {
        if (!isset(self::$_sigHandlers[$signal])) {
            // The signal should be defined already
            self::log(self::LOG_NOTICE, "You can only overrule a ".
                "handler that has been defined already.", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }
        
        // Overwrite on existance
        self::$_sigHandlers[$signal] = $handler;
        return true;
    }//end setSigHandler()

    /**
     * Sets any option found in $_optionDefinitions
     * Public interface to talk with with private option methods
     * 
     * @param string $name  Name of the Option
     * @param mixed  $value Value of the Option
     *
     * @return boolean
     */
    static public function setOption($name, $value)
    {
        if (!self::_optionObjSetup()) {
            return false;
        }
                
        return self::$_optObj->optionSet($name, $value);
    }//end setOption()    
    
    /**
     * Sets an array of options found in $_optionDefinitions
     * Public interface to talk with with private option methods
     * 
     * @param array $use_options Array with Options
     *
     * @return boolean
     */
    static public function setOptions($use_options)
    {
        if (!self::_optionObjSetup()) {
            return false;
        }
        
        return self::$_optObj->optionsSet($use_options);
    }//end setOptions()    
    
    /**
     * Gets any option found in $_optionDefinitions
     * Public interface to talk with with private option methods
     * 
     * @param string $name Name of the Option
     *
     * @return mixed
     */
    static public function getOption($name)
    {
        if (!self::_optionObjSetup()) {
            return false;
        }
                
        return self::$_optObj->optionGet($name);
    }//end getOption()    

    /**
     * Gets an array of options found
     * 
     * @return array
     */
    static public function getOptions()
    {
        if (!self::_optionObjSetup()) {
            return false;
        }
        
        return self::$_optObj->optionsGet();
    }//end setOptions()      
    
    
    /**
     * Almost every deamon requires a log file, this function can
     * facilitate that. Also handles class-generated errors, chooses 
     * either PEAR handling or PEAR-independant handling, depending on:
     * self::getOption("usePEAR").
     * Also supports PEAR_Log if you referenc to a valid instance of it
     * in self::getOption("usePEARLogInstance").
     * 
     * It logs a string according to error levels specified in array: 
     * self::$_logLevels (0 is fatal and handles daemon's death)
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
    static public function log($level, $str, $file = false, $class = false, 
        $function = false, $line = false)
    {
        // If verbosity level is not matched, don't do anything        
        if (self::getOption("logVerbosity") === null 
            || self::getOption("logVerbosity") === false) {
            // Somebody is calling log before launching daemon..
            // fair enough, but we have to init some log options
            self::_optionsInit(true);
        }
        
        if (!self::getOption("appName")) {
            // Not logging for anything without a name
            return false;
        }
        
        if ($level > self::getOption("logVerbosity")) {
            return true;
        }
        
        // Make use of a PEAR_Log() instance
        if (self::getOption("usePEARLogInstance") !== false) {
            self::getOption("usePEARLogInstance")->log($str, $level);
            return true;
        }
        
        // Save resources if arguments are passed.
        // But by falling back to debug_backtrace() it still works 
        // if someone forgets to pass them.
        if (function_exists("debug_backtrace") && ($file == false 
            || $class == false || $function == false || $line == false)) {
            $dbg_bt   = @debug_backtrace();
            $class    = (isset($dbg_bt[1]["class"])?$dbg_bt[1]["class"]:"");
            $function = (isset($dbg_bt[1]["function"])?$dbg_bt[1]["function"]:"");
            $file     = $dbg_bt[0]["file"];
            $line     = $dbg_bt[0]["line"];
        }

        // Determine what process the log is originating from and forge a logline
        //$str_ident = "@".substr(self::_daemonWhatIAm(), 0, 1)."-".posix_getpid();
        $str_date  = "[".date("M d H:i:s")."]"; 
        $str_level = str_pad(self::$_logLevels[$level]."", 8, " ", STR_PAD_LEFT);
        $log_line  = $str_date." ".$str_level.": ".$str; // $str_ident
        if ($level < self::LOG_NOTICE) {
            $log_line .= " [l:".$line."]"; 
        }
        
        $non_debug     = ($level < self::LOG_DEBUG);
        $log_succeeded = true;
        $log_echoed    = false;
        
        if (!self::daemonIsInBackground() && $non_debug && !$log_echoed) {
            // It's okay to echo if you're running as a foreground process.
            // Maybe the command to write an init.d file was issued.
            // In such a case it's important to echo failures to the 
            // STDOUT
            echo $log_line."\n";
            $log_echoed = true;
            // but still try to also log to file for future reference
        } 
        
        // 'Touch' logfile 
        if (!file_exists(self::getOption("logLocation"))) {
            file_put_contents(self::getOption("logLocation"), "");
        }
        
        // Not writable even after touch? Allowed to echo again!!
        if (!is_writable(self::getOption("logLocation")) 
            && $non_debug && !$log_echoed) { 
            echo $log_line."\n";
            $log_echoed    = true;
            $log_succeeded = false;
        } 
        
        // Append to logfile
        if (!file_put_contents(self::getOption("logLocation"), 
            $log_line."\n", FILE_APPEND)) {
            $log_succeeded = false;
        }
        
        // These are pretty serious errors
        if ($level < self::LOG_WARNING) {
            // So Throw an exception
            if (self::getOption("usePEAR")) {
                throw new System_Daemon_Exception($log_line);
            }
            // An emergency logentry is reason for the deamon to 
            // die immediately 
            if ($level == self::LOG_EMERG) {
                self::_daemonDie();
            }
        }
        
        return $log_succeeded;
        
    }//end log()    

    /**
     * Uses OS class to writes an: 'init.d' script on the filesystem
     *  
     * @param boolean $overwrite May the existing init.d file be overwritten?
     * 
     * @return boolean
     */
    static public function osInitDWrite( $overwrite=false )
    {
        // Init vars (needed for init.d script)
        if (self::_optionsInit(false) === false) {
            return false;
        }
        
        // Copy properties to OS object
        if (!System_Daemon_OS::setProperties(self::$_options)) {
            self::log(self::LOG_WARNING, "Unable to set all required ".
                "properties for init.d file");
            return false;
        }
        
        // Try to write init.d 
        if (!System_Daemon_OS::initDWrite($overwrite)) {
            //self::log(self::LOG_WARNING, "Unable to create startup file.");
            return false; 
        }        
    }//end osInitDWrite()       
    
    /**
     * Default signal handler.
     * You can overrule various signals with the 
     * setSigHandler() method
     *
     * @param integer $signo The posix signal received.
     * 
     * @return void
     * @see setSigHandler()
     * @see $_sigHandlers
     */
    static public function daemonHandleSig( $signo )
    {
        // Must be public or else will throw a 
        // fatal error: Call to private method 
         
        self::log(self::LOG_DEBUG, self::getOption("appName").
            " daemon received signal: ".$signo, 
            __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            
        switch ($signo) {
        case SIGTERM:
            // handle shutdown tasks
            if (self::daemonIsInBackground()) {
                self::_daemonDie();
            } else {
                exit;
            }
            break;
        case SIGHUP:
            // handle restart tasks
            self::log(self::LOG_INFO, self::getOption("appName").
                " daemon received signal: restart", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            break;
        case SIGCHLD:
            self::log(self::LOG_INFO, self::getOption("appName").
                " daemon received signal: hold", 
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
    static public function daemonIsInBackground()
    {
        return self::$_processIsChild;
    }//end daemonIsInBackground()
    
    /**
     * Wether the our daemon is being killed, you might 
     * want to include this in your loop
     * 
     * @return boolean
     */
    static public function daemonIsDying()
    {
        return self::$_daemonIsDying;
    }//end daemonIsDying() 

    /**
     * Check if a previous process with same pidfile was already running
     *
     * @return boolean
     */
    static public function daemonIsRunning() 
    {
        if(!file_exists(self::getOption("appPidLocation"))) return false;
        $pid = @file_get_contents(self::getOption("appPidLocation"));

        if ($pid !== false) {
            // Ping app
            if (!posix_kill(intval($pid), 0)) {
                // Not responding so unlink pidfile
                @unlink(self::getOption("appPidLocation"));
                self::log(self::LOG_WARNING, "".self::getOption("appName").
                    " daemon orphaned pidfile ".
                    "found and removed: ".self::getOption("appPidLocation"), 
                    __FILE__, __CLASS__, __FUNCTION__, __LINE__);
                return false;
            } else {
                return true;
            }
        } else {
            return false;
        }
    }//end daemonIsRunning()

    
    
    /**
     * Put the running script in background
     *
     * @return void
     */
    static private function _daemonBecome() 
    {

        self::log(self::LOG_INFO, "starting ".self::getOption("appName").
            " daemon, output in: ". 
            self::getOption("logLocation"), 
            __FILE__, __CLASS__, __FUNCTION__, __LINE__);
        
        // Important for daemons
        // See http://nl2.php.net/manual/en/function.pcntl-signal.php
        declare(ticks = 1);
        
        // Setup signal handlers
        // Handlers for individual signals can be overrulled with
        // setSigHandler()
        foreach (self::$_sigHandlers as $signal=>$handler) {
            pcntl_signal($signal, $handler);
        }
        
        // Allowed?
        if (self::daemonIsRunning()) {
            self::log(self::LOG_EMERG, "".self::getOption("appName").
                " daemon is still running. ".
                "exiting", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
        }
        
        // Reset Process Information
        self::$_safeMode       = ((boolean)@ini_get("safe_mode") === false) ? 
            false : true;
        self::$_processId      = 0;
        self::$_processIsChild = false;
        
        // Fork process!
        if (!self::_daemonFork()) {
            self::log(self::LOG_EMERG, "".self::getOption("appName").
                " daemon was unable to fork", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
        }

        // Assume specified identity (uid & gid)
        if (!posix_setuid(self::getOption("appRunAsUID")) || 
            !posix_setgid(self::getOption("appRunAsGID"))) {
            $lvl = self::LOG_CRIT;
            $swt = "off";
            if (self::getOption("appDieOnIdentityCrisis")) {
                $lvl = self::LOG_EMERG;
                $swt = "on";
            }
            
            self::log($lvl, "".self::getOption("appName").
                " daemon was unable assume ".
                "identity (uid=".self::getOption("appRunAsUID").", gid=".
                self::getOption("appRunAsGID").") ".
                "and appDieOnIdentityCrisis was ". $swt, 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
        }

        // Additional PID succeeded check
        if (!is_numeric(self::$_processId) || self::$_processId < 1) {
            self::log(self::LOG_EMERG, "".self::getOption("appName").
                " daemon didn't have a valid ".
                "pid: '".self::$_processId."'", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
        }
         
        if (!file_put_contents(self::getOption("appPidLocation"), 
            self::$_processId)) {
            self::log(self::LOG_EMERG, "".self::getOption("appName").
                " daemon was unable ".
                "to write to pidfile: ".self::getOption("appPidLocation")."", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
        }
        
        // System settings
        if (!self::$_safeMode) {       
            $options = self::getOptions();
            if (is_array($options)) {
                foreach ($options as $name=>$value) {
                    if (substr($name, 0, 3) == "sys" ) {
                        ini_set($name, $value);
                    }
                }
            }
        }
        set_time_limit(0);        
        ob_implicit_flush();        
        

        // Change dir & umask
        @chdir(self::getOption("appDir"));
        @umask(0);
    }//end _daemonBecome()
    
    /**
     * Fork process and kill parent process, the heart of the 'daemonization'
     *
     * @return boolean
     */
    static private function _daemonFork()
    {
        self::log(self::LOG_DEBUG, "forking ".self::getOption("appName").
            " daemon", 
            __FILE__, __CLASS__, __FUNCTION__, __LINE__);
        $pid = pcntl_fork();
        if ( $pid == -1 ) {
            // Error
            self::log(self::LOG_WARNING, "".self::getOption("appName").
                " daemon could not be forked", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;
        } else if ($pid) {
            // Parent
            self::log(self::LOG_DEBUG, "ending ".self::getOption("appName").
                " parent process", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            // Die without attracting attention
            exit();
        } else {
            // Child
            self::$_processIsChild = true;
            self::$_daemonIsDying  = false;
            self::$_processId      = posix_getpid();
            return true;
        }
    }//end _daemonFork()

    /**
     * Return what the current process is: child or parent
     *
     * @return string
     */
    static private function _daemonWhatIAm()
    {
        return (self::daemonIsInBackground()?"child":"parent");
    }//end _daemonWhatIAm()

    /**
     * Sytem_Daemon::_daemonDie()
     * Kill the daemon
     *
     * @return void
     */
    static private function _daemonDie()
    {
        if (!self::daemonIsDying()) {
            self::$_daemonIsDying = true;
            if (!self::daemonIsInBackground() || 
                !file_exists(self::getOption("appPidLocation"))) {
                self::log(self::LOG_INFO, "Not stopping ".
                    self::getOption("appName").
                    ", daemon was not running",
                    __FILE__, __CLASS__, __FUNCTION__, __LINE__);
                return false;
            }
            
            @unlink(self::getOption("appPidLocation"));
            exit();
        }
    }//end _daemonDie()
    
    
    /**
     * Sets up Option Object instance
     *
     * @return boolean
     */
    static private function _optionObjSetup() 
    {
        // Create Option Object if nescessary
        if (self::$_optObj === false) {
            self::$_optObj = new System_Daemon_Options(self::$_optionDefinitions);
        }
        
        // Still false? This was an error!
        if (self::$_optObj === false) {
            self::log(self::LOG_EMERG, "Unable to setup Options object. ".
                "You must provide valid option definitions");
            return false;
        } 
        
        return true;
    }
    
    /**
     * Checks if all the required options are set.
     * Initializes, sanitizes & defaults unset variables
     * 
     * @param boolean $premature Whether to do a premature option init
     * 
     * @return mixed integer or boolean
     */
    static private function _optionsInit($premature=false) 
    {
        if (!self::_optionObjSetup()) {
            return false;
        }
        
        return self::$_optObj->optionsInit($premature);        
    }//end _optionsInit()   

}//end class
?>