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
 * @author    Kevin <kevin@vanzonneveld.net>
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
    static protected $processId = 0;

    /**
     * Wether the our daemon is being killed
     *
     * @var boolean
     */
    static protected $daemonIsDying = false;    
    
    /**
     * Wether the current process is a forked child
     *
     * @var boolean
     */
    static protected $processIsChild = false;
    
    /**
     * Wether SAFE_MODE is on or off. This is important for ini_set
     * behavior
     *
     * @var boolean
     */
    static protected $safe_mode = false;
    
    /**
     * Available log levels
     *
     * @var array
     */
    static protected $logLevels = array(
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
     * Wether all the options have been initialized
     *
     * @var boolean
     */
    static protected $optionsAreInitialized = false;
    
    /**
     * Definitions for all Options
     *
     * @var array
     * @see optionGet()
     * @see optionValidate()
     * @see optionSet()
     * @see optionSetDefault()
     * @see optionsSet()
     * @see $optionDefinitions
     * @see $optionsAreInitialized
     * @see $_options
     */
    static protected $optionDefinitions = array(
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
            "type" => "integer",
            "default" => 0,
            "punch" => "Maximum execution time of each script in seconds",
            "detail" => "0 is infinite"
        ),
        "sysMaxInputTime" => array(
            "type" => "integer",
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
     * Keep track of active state for all Options
     *
     * @var array
     * @see optionGet()
     * @see optionValidate()
     * @see optionSet()
     * @see optionSetDefault()
     * @see optionsSet()
     * @see $optionDefinitions
     * @see $_options
     */
    static private $_options = array();
    
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
     * @see optionsInit()
     * @see _daemonBecome()
     */
    static public function start()
    {        
        
        // Quickly initialize some defaults like usePEAR 
        // by adding the $premature flag
        self::optionsInit(true);
        
        // To run as a part of PEAR
        if (self::$_options["usePEAR"]) {
            include_once "PEAR.php";
            include_once "PEAR/Exception.php";
            
            if (class_exists('System_Daemon_Exception', true) === false) {
                throw new Exception('Class System_Daemon_Exception not found');
            }            
        }
        
        // Check the PHP configuration
        if (!defined("SIGHUP")) {
            $msg = "PHP is compiled without --enable-pcntl directive";
            if (self::$_options["usePEAR"]) {
                throw new System_Daemon_Exception($msg);
            } else {
                trigger_error($msg, E_USER_ERROR);
            }
        }        
        
        // Check for CLI
        if ((php_sapi_name() != 'cli')) {
            $msg = "You can only create daemon from the command line";
            if (self::$_options["usePEAR"]) {
                throw new System_Daemon_Exception($msg);
            } else {
                trigger_error($msg, E_USER_ERROR);
            }
        }
        
        // Initialize & check variables
        if (self::optionsInit() === false) {
            $msg = "Crucial options are not set. Review log.";
            if (self::$_options["usePEAR"]) {
                throw new System_Daemon_Exception($msg);
            } else {
                trigger_error($msg, E_USER_ERROR);
            } 
        }
        
        // Debugging!
        print_r(self::$_options);
        die();
        
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
            self::$_options["appName"]." daemon", 
            __FILE__, __CLASS__, __FUNCTION__, __LINE__);
        self::_daemonDie();
    }//end stop()

    /**
     * Retrieves any option found in $optionDefinitions
     * 
     * @param string $name Name of the Option
     *
     * @return boolean
     * @see optionValidate()
     * @see optionSet()
     * @see optionSetDefault()
     * @see optionsSet()
     * @see $optionDefinitions
     * @see $optionAreInitialized
     * @see $_options
     */
    static public function optionGet($name)
    {
        return self::$_options[$name];
    }//end optionGet()    
    
    /**
     * Validates any option found in $optionDefinitions
     * 
     * @param string $name    Name of the Option
     * @param mixed  $value   Value of the Option
     * @param string &$reason Why something does not validate
     *
     * @return boolean
     * @see optionGet()
     * @see optionSet()
     * @see optionSetDefault()
     * @see optionsSet()
     * @see $optionDefinitions
     * @see $optionAreInitialized
     * @see $_options
     */
    static public function optionValidate($name, $value, &$reason="")
    {
        $reason = false;
        
        if (!$reason && !isset(self::$optionDefinitions[$name])) {
            $reason = "Option ".$name." not found in definitions";
        }
        
        $definition = self::$optionDefinitions[$name];
        
        if (!$reason && !isset($definition["type"])) {
            $reason = "Option ".$name.":type not found in definitions";
        }
        
        // Compile array of allowd main & subtypes
        $allowedTypes = self::allowedTypes($definition["type"]);
        
        // Loop over main & subtypes to detect matching format
        if (!$reason) {
            $type_valid = false;
            foreach ($allowedTypes as $type_a=>$sub_types) {
                foreach ($sub_types as $type_b) {
                    
                    // Determine range based on subtype
                    // Range is used to contain an integer or strlen 
                    // between min-max
                    $parts = explode("-", $type_b);
                    $from  = $to = false;
                    if (count($parts) == 2 ) {
                        $from   = $parts[0];
                        $to     = $parts[1];
                        $type_b = "range";
                    }
            
                    switch ($type_a) {
                    case "boolean":
                        $type_valid = is_bool($value);
                        break;
                    case "object":
                        $type_valid = is_object($value) || is_resource($value);
                        break;
                    case "string":
                        switch ($type_b) {
                        case "email":
                            $exp  = "^[a-z0-9]+([._-][a-z0-9]+)*@([a-z0-9]+";
                            $exp .= "([._-][a-z0-9]+))+$";
                            if (eregi($exp, $value)) {
                                $type_valid = true;
                            }
                            break;
                        case "unix":
                            if (self::strIsUnix($value)) {
                                $type_valid = true;
                            }
                            break;
                        case "existing_dirpath":
                            if (is_dir($value)) {
                                $type_valid = true;
                            }
                            break;
                        case "existing_filepath":
                            if (is_file($value)) {
                                $type_valid = true;
                            }
                            break;
                        case "creatable_filepath":
                            if (is_dir(dirname($value)) 
                                && is_writable(dirname($value))) {
                                $type_valid = true;
                            }
                            break;
                        case "normal":
                        default: 
                            // String?
                            if (!is_resource($value) && !is_array($value) 
                                && !is_object($value)) {
                                // Range?
                                if ($from === false && $to === false) {
                                    $type_valid = true;
                                } else {
                                    // Enfore range as well
                                    if (strlen($value) >= $from 
                                        && strlen($value) <= $to) {
                                        $type_valid = true;
                                    }
                                }
                            }
                            break;
                        }
                        break;
                    case "number":
                        switch ($type_b) {
                        default:
                        case "normal":
                            // Numeric?
                            if (is_numeric($value)) {
                                // Range ?
                                if ($from === false && $to === false) {
                                    $type_valid = true;
                                } else {
                                    // Enfore range as well
                                    if ($value >= $from && $value <= $to) {
                                        $type_valid = true;
                                    }
                                }
                            }
                            break;                            
                        }
                        break;
                    default:
                        self::log(self::LOG_CRIT, "Type ".
                            $type_a." not defined");
                        break;
                    }                
                }
            }
        }
        
        if (!$type_valid) {
            $reason = "Option ".$name." does not match type: ".
                $definition["type"]."";
        }
        
        if ($reason !== false) {
            return false;
        }
        
        return true;
    }//end optionValidate()    
    
    /**
     * Sets any option found in $optionDefinitions
     * 
     * @param string $name  Name of the Option
     * @param mixed  $value Value of the Option
     *
     * @return boolean
     * @see optionGet()
     * @see optionValidate()
     * @see optionSetDefault()
     * @see optionsSet()
     * @see $optionDefinitions
     * @see $optionAreInitialized
     * @see $_options
     */
    static public function optionSet($name, $value)
    {
        // not validated?
        if (!self::optionValidate($name, $value, $reason)) {
            // default not used or failed as well!
            self::log(self::LOG_NOTICE, "Option ".$name." invalid: ".$reason);
            return false;
        }
        
        self::$_options[$name] = $value;
    }//end optionSet()

    
    /**
     * Sets any option found in $optionDefinitions to its default value
     * 
     * @param string $name Name of the Option
     *
     * @return boolean
     * @see optionGet()
     * @see optionValidate()
     * @see optionSet()
     * @see optionsSet()
     * @see $optionDefinitions
     * @see $optionAreInitialized
     * @see $_options
     */
    static public function optionSetDefault($name)
    {
        if (!isset(self::$optionDefinitions[$name])) {
            return false;
        }        
        $definition = self::$optionDefinitions[$name];

        if (!isset($definition["type"])) {
            return false;
        }
        if (!isset($definition["default"])) {
            return false;
        }
        
        // Compile array of allowd main & subtypes
        $allowedTypes = self::allowedTypes($definition["type"]);        
        
        $type  = $definition["type"];
        $value = $definition["default"];

        if (isset($allowedTypes["string"]) && !is_bool($value)) {
            // Replace variables
            $value = preg_replace_callback('/\{([^\{\}]+)\}/is', 
                array("self", "_optionReplaceVariables"), $value);
            
            // Replace functions
            $value = preg_replace_callback('/\@([\w_]+)\(([^\)]+)\)/is', 
                array("self", "_optionReplaceFunctions"), $value);
        }
                        
        self::$_options[$name] = $value;
        return true;
    }//end optionSetDefault()    
    
    /**
     * Sets an array of options found in $optionDefinitions
     * 
     * @param array $use_options Array with Options
     *
     * @return boolean
     * @see optionGet()
     * @see optionValidate()
     * @see optionSet()
     * @see optionSetDefault()
     * @see $optionDefinitions
     * @see $optionAreInitialized
     * @see $_options
     */
    static public function optionsSet($use_options)
    {
        foreach ($use_options as $name=>$value) {
            if (!self::optionSet($name, $value)) {
                return false;
            }
        }
        return true;
    }//end optionsSet()
    
    
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
            // the signal should be defined already
            self::log(self::LOG_NOTICE, "You can only overrule a ".
                "handler that has been defined already.", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }
        
        // overwrite on existance
        self::$_sigHandlers[$signal] = $handler;
        return true;
    }//end setSigHandler()
    
    /**
     * Almost every deamon requires a log file, this function can
     * facilitate that. Also handles class-generated errors, chooses 
     * either PEAR handling or PEAR-independant handling, depending on:
     * self::$_options["usePEAR"].
     * Also supports PEAR_Log if you referenc to a valid instance of it
     * in self::$_options["usePEARLogInstance"].
     * 
     * It logs a string according to error levels specified in array: 
     * self::$logLevels (0 is fatal and handles daemon's death)
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
        
        if (!isset(self::$_options["logVerbosity"])) {
            // Somebody is calling log before launching daemon..
            // fair enough, but we have to init some log options
            self::optionsInit(true);
        }
        
        if (!isset(self::$_options["appName"])) {
            // Not logging for anything without a name
            return false;
        }
        
        if ($level > self::$_options["logVerbosity"]) {
            return true;
        }
        
        // Make use of a PEAR_Log() instance
        if (self::$_options["usePEARLogInstance"] !== false) {
            self::$_options["usePEARLogInstance"]->log($str, $level);
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
        $str_level = str_pad(self::$logLevels[$level]."", 8, " ", STR_PAD_LEFT);
        $log_line  = $str_date." ".$str_level.": ".$str; // $str_ident
        
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
        if (!file_exists(self::$_options["logLocation"])) {
            file_put_contents(self::$_options["logLocation"], "");
        }
        
        // Not writable even after touch? Allowed to echo again!!
        if (!is_writable(self::$_options["logLocation"]) 
            && $non_debug && !$log_echoed) { 
            echo $log_line."\n";
            $log_echoed    = true;
            $log_succeeded = false;
        } 
        
        // Append to logfile
        if (!file_put_contents(self::$_options["logLocation"], 
            $log_line."\n", FILE_APPEND)) {
            $log_succeeded = false;
        }
        
        // These are pretty serious errors
        if ($level < self::LOG_WARNING) {
            // So Throw an exception
            if (self::$_options["usePEAR"]) {
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
         
        self::log(self::LOG_DEBUG, self::$_options["appName"].
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
            self::log(self::LOG_INFO, self::$_options["appName"].
                " daemon received signal: restart", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            break;
        case SIGCHLD:
            self::log(self::LOG_INFO, self::$_options["appName"].
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
        return self::$processIsChild;
    }//end daemonIsInBackground()
    
    /**
     * Wether the our daemon is being killed, you might 
     * want to include this in your loop
     * 
     * @return boolean
     */
    static public function daemonIsDying()
    {
        return self::$daemonIsDying;
    }//end daemonIsDying()
    
    /**
     * Uses OS class to writes an: 'init.d' script on the filesystem
     *  
     * @param boolean $overwrite May the existing init.d file be overwritten?
     * 
     * @return boolean
     */
    static public function osInitDWrite( $overwrite=false )
    {
        // init vars (needed for init.d script)
        if (self::optionsInit() === false) {
            return false;
        }
        
        $properties                   = array();
        $properties["appName"]        = self::$_options["appName"];
        $properties["appDescription"] = self::$_options["appDescription"];
        $properties["authorName"]     = self::$_options["authorName"];
        $properties["authorEmail"]    = self::$_options["authorEmail"];
            
        try {
            // copy properties to OS object
            System_Daemon_OS::setProperties($properties);
            
            // try to write init.d 
            $ret = System_Daemon_OS::initDWrite($overwrite);
        } catch (System_Daemon_OS_Exception $e) {
            // Catch-all for System_Daemon_OS errors...
            self::log(self::LOG_WARNING, "Unable to create startup file: ".
                $e->getMessage());
        }
    }//end osInitDWrite()    

    
    
    /**
     * Put the running script in background
     *
     * @return void
     */
    static private function _daemonBecome() 
    {

        self::log(self::LOG_INFO, "starting ".self::$_options["appName"].
            " daemon, output in: ". 
            self::$_options["logLocation"], 
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
            self::log(self::LOG_EMERG, "".self::$_options["appName"].
                " daemon is still running. ".
                "exiting", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
        }

        // Fork process!
        if (!self::_daemonFork()) {
            self::log(self::LOG_EMERG, "".self::$_options["appName"].
                " daemon was unable to fork", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
        }

        // Assume specified identity (uid & gid)
        if (!posix_setuid(self::$_options["appRunAsUID"]) || 
            !posix_setgid(self::$_options["appRunAsGID"])) {
            $lvl = self::LOG_CRIT;
            $swt = "off";
            if (self::$_options["appDieOnIdentityCrisis"]) {
                $lvl = self::LOG_EMERG;
                $swt = "on";
            }
            
            self::log($lvl, "".self::$_options["appName"].
                " daemon was unable assume ".
                "identity (uid=".self::$_options["appRunAsUID"].", gid=".
                self::$_options["appRunAsGID"].") ".
                "and appDieOnIdentityCrisis was ". $swt, 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
        }

        // Additional PID succeeded check
        if (!is_numeric(self::$processId) || self::$processId < 1) {
            self::log(self::LOG_EMERG, "".self::$_options["appName"].
                " daemon didn't have a valid ".
                "pid: '".self::$processId."'", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
        }
         
        if (!file_put_contents(self::$_options["appPidLocation"], 
            self::$processId)) {
            self::log(self::LOG_EMERG, "".self::$_options["appName"].
                " daemon was unable ".
                "to write to pidfile: ".self::$_options["appPidLocation"]."", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
        }
        
        // System settings
        self::$safe_mode = ((boolean)@ini_get("safe_mode") === false) ? false : true;
        self::$processId      = 0;
        self::$processIsChild = false;
        if (!self::$safe_mode) {            
            foreach (self::$_options as $name=>$value) {
                if (substr($name, 0, 3) == "sys" ) {
                    ini_set($name, $value);
                }
            }
        }
        set_time_limit(0);        
        ob_implicit_flush();        
        

        // Change dir & umask
        @chdir(self::$_options["appDir"]);
        @umask(0);
    }//end _daemonBecome()
    
    /**
     * Fork process and kill parent process, the heart of the 'daemonization'
     *
     * @return boolean
     */
    static private function _daemonFork()
    {
        self::log(self::LOG_DEBUG, "forking ".self::$_options["appName"].
            " daemon", 
            __FILE__, __CLASS__, __FUNCTION__, __LINE__);
        $pid = pcntl_fork();
        if ( $pid == -1 ) {
            // error
            self::log(self::LOG_WARNING, "".self::$_options["appName"].
                " daemon could not be forked", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;
        } else if ($pid) {
            // parent
            self::log(self::LOG_DEBUG, "ending ".self::$_options["appName"].
                " parent process", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            // die without attracting attention
            exit();
        } else {
            // child
            self::$processIsChild = true;
            self::$daemonIsDying  = false;
            self::$processId      = posix_getpid();
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
            self::$daemonIsDying       = true;
            self::$optionsAreInitialized = false;
            if (!self::daemonIsInBackground() || 
                !file_exists(self::$_options["appPidLocation"])) {
                self::log(self::LOG_INFO, "Not stopping ".
                    self::$_options["appName"].
                    ", daemon was not running",
                    __FILE__, __CLASS__, __FUNCTION__, __LINE__);
                return false;
            }
            
            @unlink(self::$_options["appPidLocation"]);
            exit();
        }
    }//end _daemonDie()
    

    /**
     * Callback function to replace variables in defaults
     *
     * @param array $matches Matched functions
     * 
     * @return string
     */
    static private function _optionReplaceVariables($matches)
    {
        // Init
        $allowedVars = array(
            "SERVER.SCRIPT_NAME", 
            "OPTIONS.*"
        );
        $filterVars  = array(
            "SERVER.SCRIPT_NAME"=>array("realpath")
        );
        
        $fullmatch          = array_shift($matches);
        $fullvar            = array_shift($matches);
        $parts              = explode(".", $fullvar);
        list($source, $var) = $parts;
        $var_use            = false;
        $var_key            = $source.".".$var; 
        
        // Allowed
        if (!in_array($var_key, $allowedVars) 
            && !in_array($source.".*", $allowedVars)) {
            return "FORBIDDEN_VAR_".$var_key;
        }
        
        // Mapping of textual sources to real sources
        if ($source == "SERVER") {
            $source_use = &$_SERVER;
        } elseif ($source == "OPTIONS") {
            $source_use = &self::$_options; 
        } else {
            $source_use = false;
        }
        
        // Exists?
        if ($source_use === false) {
            return "UNUSABLE_VARSOURCE_".$source;
        }
        if (!isset($source_use[$var])) { 
            return "NONEXISTING_VAR_".$var_key;     
        }
        
        $var_use = $source_use[$var];
        
        // Filtering
        if (isset($filterVars[$var_key]) && is_array($filterVars[$var_key])) {
            foreach ($filterVars[$var_key] as $filter_function) {
                if (!function_exists($filter_function)) {
                    return "NONEXISTING_FILTER_".$filter_function;
                }
                $var_use = call_user_func($filter_function, $var_use);
            }
        }        
        
        return $var_use;        
    }
    
    /**
     * Callback function to replace function calls in defaults
     *
     * @param array $matches Matched functions
     * 
     * @return string
     */
    static private function _optionReplaceFunctions($matches)
    {
        $allowedFunctions = array("basename", "dirname");
        
        $fullmatch = array_shift($matches);
        $function  = array_shift($matches);
        $arguments = $matches;
        
        if (!in_array($function, $allowedFunctions)) {
            return "FORBIDDEN_FUNCTION_".$function;            
        }
        
        if (!function_exists($function)) {
            return "NONEXISTING_FUNCTION_".$function; 
        }
        
        return call_user_func_array($function, $arguments);
    }
    
    
    /**
     * Checks if all the required options are set.
     * Initializes, sanitizes & defaults unset variables
     * 
     * @param boolean $premature Whether to do a premature option init
     *
     * @return mixed integer or boolean
     * @see optionGet()
     * @see optionValidate()
     * @see optionSet()
     * @see optionSetDefault()
     * @see optionsSet()
     * @see $optionDefinitions
     * @see $optionsAreInitialized
     * @see $_options
     */
    static protected function optionsInit($premature=false) 
    {
        // If already initialized, skip
        if (!$premature && self::$optionsAreInitialized) {
            return true;
        }
        
        $options_met = 0;
        
        foreach (self::$optionDefinitions as $name=>$definition) {
            // Skip non-required options
            if (!isset($definition["required"]) 
                || $definition["required"] !== true ) {
                continue;
            }
            
            // Required options remain
            if (!isset(self::$_options[$name])) {                
                if (!self::optionSetDefault($name) && !$premature) {
                    self::log(self::LOG_WARNING, "Required option: ".$name. 
                        " not set. No default value available either.");
                    return false;
                } 
            }
            
            $options_met++;
        }
                
        if (!$premature) {
            self::$optionsAreInitialized = true;
        }
        
        return $options_met;
        
    }//end optionsInit()    
    
    /**
     * Check if a previous process with same pidfile was already running
     *
     * @return boolean
     */
    static protected function daemonIsRunning() 
    {
        if(!file_exists(self::$_options["appPidLocation"])) return false;
        $pid = @file_get_contents(self::$_options["appPidLocation"]);

        if ($pid !== false) {
            // Ping app
            if (!posix_kill(intval($pid), 0)) {
                // Not responding so unlink pidfile
                @unlink(self::$_options["appPidLocation"]);
                self::log(self::LOG_WARNING, "".self::$_options["appName"].
                    " daemon orphaned pidfile ".
                    "found and removed: ".self::$_options["appPidLocation"], 
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
     * Compile array of allowed types
     * 
     * @param string $str String that contains allowed type information
     * 
     * @return array      
     */
    static protected function allowedTypes($str) 
    {
        $allowedTypes = array();
        $raw_types    = explode("|", $str);
        foreach ($raw_types as $raw_type) {
            $raw_subtypes = explode("/", $raw_type);
            $type_a       = array_shift($raw_subtypes);
            if (!count($raw_subtypes)) {
                $raw_subtypes = array("normal");
            } 
            $allowedTypes[$type_a] = $raw_subtypes;
        }
        return $allowedTypes;
    }
    
    /**
     * Check if a string has a unix proof format (stripped spaces, 
     * special chars, etc)
     *
     * @param string $str What string to test for unix compliance
     * 
     * @return boolean
     */   
    static protected function strIsUnix( $str )
    {
        return preg_match('/^[a-z0-9_]+$/', $str);
    }//end strIsUnix()

    /**
     * Convert a string to a unix proof format (strip spaces, 
     * special chars, etc)
     * 
     * @param string $str What string to make unix compliant
     * 
     * @return string
     */
    static protected function strToUnix( $str )
    {
        return preg_replace('/[^0-9a-z_]/', '', strtolower($str));
    }//end strToUnix()
    

}//end class
?>