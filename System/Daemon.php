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
 * like System_Daemon::start()
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
     * System is unusable (will throw a System_Daemon_Exception as well)
     */
    const LOG_EMERG = 0;
    
    /**
     * Immediate action required (will throw a System_Daemon_Exception as well)
     */ 
    const LOG_ALERT = 1;
    
    /**
     * Critical conditions (will throw a System_Daemon_Exception as well)
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
    static protected $_processId = 0;

    /**
     * Wether the our daemon is being killed
     *
     * @var boolean
     */
    static protected $_isDying = false;
    
    /**
     * Wether the current process is a forked child
     *
     * @var boolean
     */
    static protected $_processIsChild = false;
    
    /**
     * Wether SAFE_MODE is on or off. This is important for ini_set
     * behavior
     *
     * @var boolean
     */
    static protected $_safeMode = false;
    
    /**
     * Available log levels
     *
     * @var array
     */
    static protected $_logLevels = array(
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
     * Available PHP error levels and their meaning in POSIX loglevel terms
     *
     * @var array
     */
    static protected $_logPhpMapping = array(
        E_ERROR => self::LOG_ERR,
        E_WARNING => self::LOG_WARNING,
        E_PARSE => self::LOG_EMERG,
        E_NOTICE => self::LOG_DEBUG,
        E_CORE_ERROR => self::LOG_EMERG,
        E_CORE_WARNING => self::LOG_WARNING,
        E_COMPILE_ERROR => self::LOG_EMERG,
        E_COMPILE_WARNING => self::LOG_WARNING,
        E_USER_ERROR => self::LOG_ERR,
        E_USER_WARNING => self::LOG_WARNING,
        E_USER_NOTICE => self::LOG_DEBUG
    );


    /**
     * Holds Option Object
     * 
     * @var mixed object or boolean
     */
    static protected $_optObj = false;
    
    /**
     * Holds OS Object
     * 
     * @var mixed object or boolean
     */
    static protected $_osObj = false;
    
    /**
     * Definitions for all Options
     *
     * @var array
     * @see setOption()
     * @see getOption()
     */
    static protected $_optionDefinitions = array(
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
        "logPhpErrors" => array(
            "type" => "boolean",
            "default" => true,
            "punch" => "Reroute PHP errors to log function",
            "detail" => "",
            "required" => true
        ),
        "logFilePosition" => array(
            "type" => "boolean",
            "default" => false,
            "punch" => "Show file in which the log message was generated",
            "detail" => "",
            "required" => true
        ),
        "logLinePosition" => array(
            "type" => "boolean",
            "default" => true,
            "punch" => "Show the line number in which the log message was generated",
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
            "type" => "string/unix_filepath",
            "default" => "/var/run/{OPTIONS.appName}/{OPTIONS.appName}.pid",
            "punch" => "The pid filepath",
            "example" => "/var/run/logparser/logparser.pid",
            "detail" => "",
            "required" => true
        ),
        "appChkConfig" => array(
             "type" => "string",
             "default" => "- 99 0",
             "punch" => "chkconfig parameters for init.d",
             "detail" => "runlevel startpriority stoppriority"
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
    static protected $_sigHandlers = array(
        SIGCONT => array("System_Daemon", "defaultSigHandler"),
        SIGALRM => array("System_Daemon", "defaultSigHandler"),
        SIGINT => array("System_Daemon", "defaultSigHandler"),
        SIGABRT => array("System_Daemon", "defaultSigHandler"),
        SIGTERM => array("System_Daemon", "defaultSigHandler"),
        SIGHUP => array("System_Daemon", "defaultSigHandler"),
        SIGUSR1 => array("System_Daemon", "defaultSigHandler"),
        SIGCHLD => array("System_Daemon", "defaultSigHandler"),
        SIGPIPE => SIG_IGN,
    );
    

    
    /**
     * Making the class non-abstract with a protected constructor does a better
     * job of preventing instantiation than just marking the class as abstract.
     * 
     * @see start()
     */
    protected function __construct()
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
        } else if(self::fileExistsInPath($path)) {
            // Everything else.
            include $path;
        }

    }//end autoload()
    
    
    /**
     * Spawn daemon process.
     * 
     * @return boolean
     * @see stop()
     * @see autoload()
     * @see _optionsInit()
     * @see _summon()
     */
    static public function start()
    {        
        // Quickly initialize some defaults like usePEAR 
        // by adding the $premature flag
        self::_optionsInit(true);

        if (self::getOption("logPhpErrors")) {
            set_error_handler(array('System_Daemon', 'phpErrors'), E_ALL);
        }

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
            $msg = "You can only create daemon from the command line (CLI-mode)";
            if (self::getOption("usePEAR")) {
                throw new System_Daemon_Exception($msg);
            } else {
                trigger_error($msg, E_USER_ERROR);
            }
        }
        
        // Check for POSIX
        if (!function_exists("posix_getpid")) {
            $msg = "PHP is compiled without --enable-posix directive";
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
        self::_summon();
        
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
        self::_die(false);
    }//end stop()
    
    /**
     * Restart daemon process.
     *
     * @return void
     * @see _die()
     */
    static public function restart()
    {
        self::log(self::LOG_INFO, "restarting ".
            self::getOption("appName")." daemon",
            __FILE__, __CLASS__, __FUNCTION__, __LINE__);
        self::_die(true);
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
            self::log(self::LOG_NOTICE, "You can only overrule one ".
                "of these handlers: ".implode(', ', self::$_sigHandlers),
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }
        
        // Overwrite on existance
        self::$_sigHandlers[$signal] = $handler;
        return true;
    }//end setSigHandler()

    /**
     * Sets any option found in $_optionDefinitions
     * Public interface to talk with with protected option methods
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
                
        return self::$_optObj->setOption($name, $value);
    }//end setOption()    
    
    /**
     * Sets an array of options found in $_optionDefinitions
     * Public interface to talk with with protected option methods
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
        
        return self::$_optObj->setOptions($use_options);
    }//end setOptions()    
    
    /**
     * Gets any option found in $_optionDefinitions
     * Public interface to talk with with protected option methods
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
                
        return self::$_optObj->getOption($name);
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
        
        return self::$_optObj->getOptions();
    }//end setOptions()      
    

    static public function phpErrors($errno, $errstr, $errfile, $errline) {
        // Ignore suppressed errors
        if (error_reporting() == 0) {
			return;
        }

        // Map PHP error level to System_Daemon log level
        if (empty(self::$_logPhpMapping[$errno])) {
            self::log(self::LOG_WARNING, 'Unknown PHP errorno: '.$errno);
            $lvl = self::LOG_ERR;
        } else {
            $lvl = self::$_logPhpMapping[$errno];
        }

        // Log it
        self::log($lvl, '[PHP Error] '.$errstr, $errfile, __CLASS__, __FUNCTION__, $errline);
        return true;
    }

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
        //$str_ident = "@".substr(self::_whatIAm(), 0, 1)."-".posix_getpid();
        $str_date  = "[".date("M d H:i:s")."]"; 
        $str_level = str_pad(self::$_logLevels[$level]."", 8, " ", STR_PAD_LEFT);
        $log_line  = $str_date." ".$str_level.": ".$str; // $str_ident
        if ($level < self::LOG_NOTICE) {
            if (self::getOption("logFilePosition")) {
                $log_line .= " [f:".$file."]";
            }
            if (self::getOption("logLinePosition")) {
                $log_line .= " [l:".$line."]";
            }
        }
        
        $non_debug     = ($level < self::LOG_DEBUG);
        $log_succeeded = true;
        $log_echoed    = false;
        
        if (!self::isInBackground() && $non_debug && !$log_echoed) {
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
        if ($level < self::LOG_ERR) {
            // So Throw an exception
            if (self::getOption("usePEAR")) {
                throw new System_Daemon_Exception($log_line);
            }
            // An emergency logentry is reason for the deamon to 
            // die immediately 
            if ($level == self::LOG_EMERG) {
                self::_die();
            }
        }
        
        return $log_succeeded;
        
    }//end log()    

    /**
     * Uses OS class to write an: 'init.d' script on the filesystem
     *  
     * @param boolean $overwrite May the existing init.d file be overwritten?
     * 
     * @return boolean
     */
    static public function writeAutoRun($overwrite=false)
    {
        // Init Options (needed for properties of init.d script)
        if (self::_optionsInit(false) === false) {
            return false;
        }
        
        // Init OS Object
        if (!self::_osObjSetup()) {
            return false;
        }
        
        // Get daemon properties
        $options = self::getOptions();
        
        // Try to write init.d 
        if (($res = self::$_osObj->writeAutoRun($options, $overwrite)) === false) {
            if (is_array(self::$_osObj->errors)) {
                foreach (self::$_osObj->errors as $error) {
                    self::log(self::LOG_NOTICE, $error);
                }
            }
            self::log(self::LOG_WARNING, "Unable to create startup file.");
            return false;
        }
        
        if ($res === true) {
            self::log(self::LOG_NOTICE, "Startup was already written");
        } else {
            self::log(self::LOG_NOTICE, "Startup written to ".$res."");
        }
        
        return true;
    }//end writeAutoRun()       
    
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
    static public function defaultSigHandler( $signo )
    {
        // Must be public or else will throw a 
        // fatal error: Call to protected method
         
        self::log(self::LOG_DEBUG, self::getOption("appName").
            " daemon received signal: ".$signo, 
            __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            
        switch ($signo) {
        case SIGTERM:
            // Handle shutdown tasks
            if (self::isInBackground()) {
                self::_die();
            } else {
                exit;
            }
            break;
        case SIGHUP:
            // Handle restart tasks
            self::log(self::LOG_INFO, self::getOption("appName").
                " daemon received signal: restart", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            break;
        case SIGCHLD:
            // A child process has died
            self::log(self::LOG_INFO, self::getOption("appName").
                " daemon received signal: child",
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            while (pcntl_wait($status, WNOHANG OR WUNTRACED) > 0) {
                usleep(1000);
            }
            break;
        default:
            // Handle all other signals
            break;
        }
    }//end defaultSigHandler()

    /**
     * Wether the class is already running in the background
     * 
     * @return boolean
     */
    static public function isInBackground()
    {
        return self::$_processIsChild;
    }//end isInBackground()
    
    /**
     * Wether the our daemon is being killed, you might 
     * want to include this in your loop
     * 
     * @return boolean
     */
    static public function isDying()
    {
        return self::$_isDying;
    }//end isDying() 

    /**
     * file_exists does not check the include paths. This function does.
     * It was not written by me, I don't know where it's from exactly.
     * Let me know if you do.
     *
     * From kvzlib.net
     *
     * @param string $file
     *
     * @return boolean
     */
    static public function fileExistsInPath($file){
        // Using explode on the include_path is three times faster than using fopen

        // no file requested?
        $file = trim($file);
        if (!$file) {
            return false;
        }

        // using an absolute path for the file?
        // dual check for Unix '/' and Windows '\',
        // or Windows drive letter and a ':'.
        $abs = ($file[0] == '/' || $file[0] == '\\' || $file[1] == ':');
        if ($abs && file_exists($file)) {
            return $file;
        }

        // using a relative path on the file
        $path = explode(PATH_SEPARATOR, ini_get('include_path'));
        foreach ($path as $base) {
            // strip Unix '/' and Windows '\'
            $target = rtrim($base, '\\/') . DIRECTORY_SEPARATOR . $file;
            if (file_exists($target)) {
                return $target;
            }
        }

        // never found it
        return false;
    }//end isDying()

    /**
     * Check if a previous process with same pidfile was already running
     *
     * @return boolean
     */
    static public function isRunning() 
    {
        if (!file_exists(self::getOption("appPidLocation"))) {
            return false;
        }
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
    }//end isRunning()

    
    
    /**
     * Put the running script in background
     *
     * @return void
     */
    static protected function _summon()
    {
        self::log(self::LOG_INFO, "starting ".self::getOption("appName")." ".
            "daemon, output in: ". 
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
        if (self::isRunning()) {
            self::log(self::LOG_EMERG, "".self::getOption("appName")." ".
                "daemon is still running. ".
                "exiting", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
        }
        
        // Reset Process Information
        self::$_safeMode       = ((boolean)@ini_get("safe_mode") === false) ? 
            false : true;
        self::$_processId      = 0;
        self::$_processIsChild = false;
        
        // Fork process!
        if (!self::_fork()) {
            self::log(self::LOG_EMERG, "".self::getOption("appName")." ".
                "daemon was unable to fork", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
        }

        // Additional PID succeeded check
        if (!is_numeric(self::$_processId) || self::$_processId < 1) {
            self::log(self::LOG_EMERG, "".self::getOption("appName")." ".
                "daemon didn't have a valid ".
                "pid: '".self::$_processId."'",
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
        }

        // Change umask
        @umask(0);

        // Write pidfile
        if (false === self::_writePid(self::getOption("appPidLocation"), self::$_processId)) {
            self::log(self::LOG_EMERG, "".self::getOption("appName")." ".
                "daemon was unable ".
                "to write pid file");
        }

        // Change identity. maybe
        if (false === self::_changeIdentity(self::getOption("appRunAsGID"), self::getOption("appRunAsUID"))) {
            // Die on fail?
            $lvl = self::getOption("appDieOnIdentityCrisis") ? self::LOG_EMERG : self::LOG_CRIT;

            self::log($lvl, "".self::getOption("appName")." ".
                "daemon was unable ".
                "to change identity");
        }

        // Change dir
        @chdir(self::getOption("appDir"));
        
    }//end _summon()

    /**
     * Determine whether pidfilelocation is valid
     *
     * @param string  $pidFilePath Pid location
     * @param boolean $log         Allow this function to log directly on error
     *
     * @return boolean
     */
    static protected function _isValidPidLocation($pidFilePath, $log = true) {
        if (empty($pidFilePath)) {
            self::log(self::LOG_ERR, "".self::getOption("appName")." ".
                "daemon encountered ".
                "an empty appPidLocation",
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }

        $pidDirPath = dirname($pidFilePath);

        $parts = explode('/', $pidDirPath);
        if (count($parts) <= 3 || end($parts) != self::getOption("appName")) {
            // like: /var/run/x.pid
            self::log(self::LOG_ERR, "".
                "Since version 0.6.3, the pidfile needs to be in it's own ".
                "subdirectory like: ".$pidDirPath."/".self::getOption("appName").
                "/".self::getOption("appName").".pid",
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }
        
        return true;
    }//end _isValidPidLocation

    static protected function _writePid($pidFilePath = null, $pid = null) {
        if (empty($pid)) {
            self::log(self::LOG_ERR, "".self::getOption("appName")." ".
                "daemon encountered ".
                "an empty pid",
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }

        if (!self::_isValidPidLocation($pidFilePath, true)) {
            return false;
        }
        
        $pidDirPath = dirname($pidFilePath);

        if (!self::_mkdirr($pidDirPath, 0755)) {
            self::log(self::LOG_ERR, "".self::getOption("appName")." ".
                "daemon was unable ".
                "to create directory: ".$pidDirPath);
            return false;
        }

        if (!file_put_contents($pidFilePath, $pid)) {
            self::log(self::LOG_ERR, "".self::getOption("appName")." ".
                "daemon was unable ".
                "to write to pidfile: ".self::getOption("appPidLocation")."",
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }

        if (!chmod($pidFilePath, 0644)) {
            self::log(self::LOG_ERR, "".self::getOption("appName")." ".
                "daemon was unable ".
                "to chmod to pidfile: ".self::getOption("appPidLocation")." ".
                "to umask: 0644",
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }

        return true;
    }//end _writePid()

    static protected function _mkdirr($dirPath, $mode) {
        is_dir(dirname($dirPath)) || self::_mkdirr(dirname($dirPath), $mode);
        return is_dir($dirPath) || @mkdir($dirPath, $mode);
    }

    /**
     * Change identity of process & resources if needed.
     *
     * @param integer $gid Group identifier (number)
     * @param integer $uid User identifier (number)
     *
     * @return boolean
     */
    static protected function _changeIdentity($gid = 0, $uid = 0) {

        // What files need to be chowned?
        $chownFiles   = array();
        if (self::_isValidPidLocation(self::getOption("appPidLocation"), true)) {
            $chownFiles[] = dirname(self::getOption("appPidLocation"));
        }
        $chownFiles[] = self::getOption("appPidLocation");
        $chownFiles[] = self::getOption("logLocation");

        // Chown pid- & log file
        // We have to change owner in case of identity change.
        // This way we can modify the files even after we're not root anymore
        foreach ($chownFiles as $filePath) {
            // Change File GID
            $doGid = (fileowner($filePath) !== $gid ? $gid : false);
            if (false !== $doGid && !@chgrp($filePath, $gid)) {
                self::log(self::LOG_ERR, "".self::getOption("appName")." ".
                    "daemon was unable ".
                    "to change group of file: ".$filePath." ".
                    "to: ".$gid,
                    __FILE__, __CLASS__, __FUNCTION__, __LINE__);
                return false;
            }

            // Change File UID
            $doUid = (fileowner($filePath) !== $uid ? $uid : false);
            if (false !== $doUid && !@chown($filePath, $uid)) {
                self::log(self::LOG_ERR, "".self::getOption("appName")." ".
                    "daemon was unable ".
                    "to change user of file: ".$filePath." ".
                    "to: ".$uid,
                    __FILE__, __CLASS__, __FUNCTION__, __LINE__);
                return false;
            }
        }
        
        // Change Process GID
        $doGid = (posix_getgid() !== $gid ? $gid : false);
        if (false !== $doGid && !@posix_setgid($gid)) {
            self::log(self::LOG_ERR, "".self::getOption("appName")." ".
                "daemon was unable ".
                "to change group of process ".
                "to: ".$gid,
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }

        // Change Process UID
        $doUid = (posix_getuid() !== $uid ? $uid : false);
        if (false !== $doUid && !@posix_setuid($uid)) {
            self::log(self::LOG_ERR, "".self::getOption("appName")." ".
                "daemon was unable ".
                "to change user of process ".
                "to: ".$uid,
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }

        return true;
    }//end _changeIdentity()

    /**
     * Fork process and kill parent process, the heart of the 'daemonization'
     *
     * @return boolean
     */
    static protected function _fork()
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
            self::$_isDying        = false;
            self::$_processId      = posix_getpid();
            return true;
        }
    }//end _fork()

    /**
     * Return what the current process is: child or parent
     *
     * @return string
     */
    static protected function _whatIAm()
    {
        return (self::isInBackground()?"child":"parent");
    }//end _whatIAm()

    /**
     * Sytem_Daemon::_die()
     * Kill the daemon
     * Keep this function as independent from complex logic as possible
     *
     * @param boolean $restart Whether to restart after die
     *
     * @return void
     */
    static protected function _die($restart = false)
    {
        if (!self::isDying()) {
            self::$_isDying = true;
            // Following caused a bug if pid couldn't Sbe written because of privileges
            // || !file_exists(self::getOption("appPidLocation"))
            if (!self::isInBackground()) {
                self::log(self::LOG_INFO, "Not stopping ".
                    self::getOption("appName").
                    ", daemon was not running",
                    __FILE__, __CLASS__, __FUNCTION__, __LINE__);
                return false;
            }

            unlink(self::getOption("appPidLocation"));

            if ($restart) {
                // Following line blocks the exit. Leaving zombie processes:
                //die(exec(join(' ', $GLOBALS['argv'])));

                // So instead we should:
                die(exec(join(' ', $GLOBALS['argv']) . ' > /dev/null &'));
            } else {
                die();
            }
        }
    }//end _die()
    
    
    /**
     * Sets up OS instance
     *
     * @return boolean
     */
    static protected function _osObjSetup()
    {
        // Create Option Object if nescessary
        if (self::$_osObj === false) {
            self::$_osObj = System_Daemon_OS::factory();
        }
        
        // Still false? This was an error!
        if (self::$_osObj === false) {
            self::log(self::LOG_EMERG, "Unable to setup OS object. ");
            return false;
        } 
        
        return true;
    }
    
    /**
     * Sets up Option Object instance
     *
     * @return boolean
     */
    static protected function _optionObjSetup()
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
    static protected function _optionsInit($premature=false)
    {
        if (!self::_optionObjSetup()) {
            return false;
        }
        
        return self::$_optObj->init($premature);        
    }//end _optionsInit()   

}//end class
?>