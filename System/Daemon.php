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
spl_autoload_register(array('System_Daemon', 'autoload'));

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
     * Whether the our daemon is being killed
     *
     * @var boolean
     */
    static protected $_isDying = false;

    /**
     * Whether the current process is a forked child
     *
     * @var boolean
     */
    static protected $_processIsChild = false;

    /**
     * Whether SAFE_MODE is on or off. This is important for ini_set
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
        self::LOG_EMERG => 'emerg',
        self::LOG_ALERT => 'alert',
        self::LOG_CRIT => 'crit',
        self::LOG_ERR => 'err',
        self::LOG_WARNING => 'warning',
        self::LOG_NOTICE => 'notice',
        self::LOG_INFO => 'info',
        self::LOG_DEBUG => 'debug',
    );

    /**
     * Available PHP error levels and their meaning in POSIX loglevel terms
     * Some ERROR constants are not supported in all PHP versions
     * and will conditionally be translated from strings to constants,
     * or else: removed from this mapping at start().
     *
     * @var array
     */
    static protected $_logPhpMapping = array(
        E_ERROR => array(self::LOG_ERR, 'Error'),
        E_WARNING => array(self::LOG_WARNING, 'Warning'),
        E_PARSE => array(self::LOG_EMERG, 'Parse'),
        E_NOTICE => array(self::LOG_DEBUG, 'Notice'),
        E_CORE_ERROR => array(self::LOG_EMERG, 'Core Error'),
        E_CORE_WARNING => array(self::LOG_WARNING, 'Core Warning'),
        E_COMPILE_ERROR => array(self::LOG_EMERG, 'Compile Error'),
        E_COMPILE_WARNING => array(self::LOG_WARNING, 'Compile Warning'),
        E_USER_ERROR => array(self::LOG_ERR, 'User Error'),
        E_USER_WARNING => array(self::LOG_WARNING, 'User Warning'),
        E_USER_NOTICE => array(self::LOG_DEBUG, 'User Notice'),
        'E_RECOVERABLE_ERROR' => array(self::LOG_WARNING, 'Recoverable Error'),
        'E_DEPRECATED' => array(self::LOG_NOTICE, 'Deprecated'),
        'E_USER_DEPRECATED' => array(self::LOG_NOTICE, 'User Deprecated'),
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
        'usePEAR' => array(
            'type' => 'boolean',
            'default' => true,
            'punch' => 'Whether to run this class using PEAR',
            'detail' => 'Will run standalone when false',
            'required' => true,
        ),
        'usePEARLogInstance' => array(
            'type' => 'boolean|object',
            'default' => false,
            'punch' => 'Accepts a PEAR_Log instance to handle all logging',
            'detail' => 'This will replace System_Daemon\'s own logging facility',
            'required' => true,
        ),

        'authorName' => array(
            'type' => 'string/0-50',
            'punch' => 'Author name',
            'example' => 'Kevin van zonneveld',
            'detail' => 'Required for forging init.d script',
        ),
        'authorEmail' => array(
            'type' => 'string/email',
            'punch' => 'Author e-mail',
            'example' => 'kevin@vanzonneveld.net',
            'detail' => 'Required for forging init.d script',
        ),
        'appName' => array(
            'type' => 'string/unix',
            'punch' => 'The application name',
            'example' => 'logparser',
            'detail' => 'Must be UNIX-proof; Required for running daemon',
            'required' => true,
        ),
        'appDescription' => array(
            'type' => 'string',
            'punch' => 'Daemon description',
            'example' => 'Parses logfiles of vsftpd and stores them in MySQL',
            'detail' => 'Required for forging init.d script',
        ),
        'appDir' => array(
            'type' => 'string/existing_dirpath',
            'default' => '@dirname({SERVER.SCRIPT_NAME})',
            'punch' => 'The home directory of the daemon',
            'example' => '/usr/local/logparser',
            'detail' => 'Highly recommended to set this yourself',
            'required' => true,
        ),
        'appExecutable' => array(
            'type' => 'string/existing_filepath',
            'default' => '@basename({SERVER.SCRIPT_NAME})',
            'punch' => 'The executable daemon file',
            'example' => 'logparser.php',
            'detail' => 'Recommended to set this yourself; Required for init.d',
            'required' => true
        ),

        'logVerbosity' => array(
            'type' => 'number/0-7',
            'default' => self::LOG_INFO,
            'punch' => 'Messages below this log level are ignored',
            'example' => '',
            'detail' => 'Not written to logfile; not displayed on screen',
            'required' => true,
        ),
        'logLocation' => array(
            'type' => 'string/creatable_filepath',
            'default' => '/var/log/{OPTIONS.appName}.log',
            'punch' => 'The log filepath',
            'example' => '/var/log/logparser_daemon.log',
            'detail' => 'Not applicable if you use PEAR Log',
            'required' => false,
        ),
        'logPhpErrors' => array(
            'type' => 'boolean',
            'default' => true,
            'punch' => 'Reroute PHP errors to log function',
            'detail' => '',
            'required' => true,
        ),
        'logFilePosition' => array(
            'type' => 'boolean',
            'default' => false,
            'punch' => 'Show file in which the log message was generated',
            'detail' => '',
            'required' => true,
        ),
        'logTrimAppDir' => array(
            'type' => 'boolean',
            'default' => true,
            'punch' => 'Strip the application dir from file positions in log msgs',
            'detail' => '',
            'required' => true,
        ),
        'logLinePosition' => array(
            'type' => 'boolean',
            'default' => true,
            'punch' => 'Show the line number in which the log message was generated',
            'detail' => '',
            'required' => true,
        ),
        'appRunAsUID' => array(
            'type' => 'number/0-65000',
            'default' => 0,
            'punch' => 'The user id under which to run the process',
            'example' => '1000',
            'detail' => 'Defaults to root which is insecure!',
            'required' => true,
        ),
        'appRunAsGID' => array(
            'type' => 'number/0-65000',
            'default' => 0,
            'punch' => 'The group id under which to run the process',
            'example' => '1000',
            'detail' => 'Defaults to root which is insecure!',
            'required' => true,
        ),
        'appPidLocation' => array(
            'type' => 'string/unix_filepath',
            'default' => '/var/run/{OPTIONS.appName}/{OPTIONS.appName}.pid',
            'punch' => 'The pid filepath',
            'example' => '/var/run/logparser/logparser.pid',
            'detail' => '',
            'required' => true,
        ),
        'appChkConfig' => array(
             'type' => 'string',
             'default' => '- 99 0',
             'punch' => 'chkconfig parameters for init.d',
             'detail' => 'runlevel startpriority stoppriority',
         ),
        'appDieOnIdentityCrisis' => array(
            'type' => 'boolean',
            'default' => true,
            'punch' => 'Kill daemon if it cannot assume the identity',
            'detail' => '',
            'required' => true,
        ),

        'sysMaxExecutionTime' => array(
            'type' => 'number',
            'default' => 0,
            'punch' => 'Maximum execution time of each script in seconds',
            'detail' => '0 is infinite',
        ),
        'sysMaxInputTime' => array(
            'type' => 'number',
            'default' => 0,
            'punch' => 'Maximum time to spend parsing request data',
            'detail' => '0 is infinite',
        ),
        'sysMemoryLimit' => array(
            'type' => 'string',
            'default' => '128M',
            'punch' => 'Maximum amount of memory a script may consume',
            'detail' => '0 is infinite',
        ),

        'runTemplateLocation' => array(
            'type' => 'string/existing_filepath',
            'default' => false,
            'punch' => 'The filepath to a custom autorun Template',
            'example' => '/etc/init.d/skeleton',
            'detail' => 'Sometimes it\'s better to stick with the OS default,
                and use something like /etc/default/<name> for customization',
        ),
    );


    /**
     * Available signal handlers
     * setSigHandler can overwrite these values individually.
     *
     * Available POSIX SIGNALS and their PHP handler functions.
     * Some SIGNALS constants are not supported in all PHP versions
     * and will conditionally be translated from strings to constants,
     * or else: removed from this mapping at start().
     *
     * 'kill -l' gives you a list of signals available on your UNIX.
     * Eg. Ubuntu:
     *
     *  1) SIGHUP      2) SIGINT      3) SIGQUIT      4) SIGILL
     *  5) SIGTRAP      6) SIGABRT      7) SIGBUS      8) SIGFPE
     *  9) SIGKILL    10) SIGUSR1    11) SIGSEGV    12) SIGUSR2
     * 13) SIGPIPE    14) SIGALRM    15) SIGTERM    17) SIGCHLD
     * 18) SIGCONT    19) SIGSTOP    20) SIGTSTP    21) SIGTTIN
     * 22) SIGTTOU    23) SIGURG      24) SIGXCPU    25) SIGXFSZ
     * 26) SIGVTALRM  27) SIGPROF    28) SIGWINCH    29) SIGIO
     * 30) SIGPWR      31) SIGSYS      33) SIGRTMIN    34) SIGRTMIN+1
     * 35) SIGRTMIN+2  36) SIGRTMIN+3  37) SIGRTMIN+4  38) SIGRTMIN+5
     * 39) SIGRTMIN+6  40) SIGRTMIN+7  41) SIGRTMIN+8  42) SIGRTMIN+9
     * 43) SIGRTMIN+10 44) SIGRTMIN+11 45) SIGRTMIN+12 46) SIGRTMIN+13
     * 47) SIGRTMIN+14 48) SIGRTMIN+15 49) SIGRTMAX-15 50) SIGRTMAX-14
     * 51) SIGRTMAX-13 52) SIGRTMAX-12 53) SIGRTMAX-11 54) SIGRTMAX-10
     * 55) SIGRTMAX-9  56) SIGRTMAX-8  57) SIGRTMAX-7  58) SIGRTMAX-6
     * 59) SIGRTMAX-5  60) SIGRTMAX-4  61) SIGRTMAX-3  62) SIGRTMAX-2
     * 63) SIGRTMAX-1  64) SIGRTMAX
     *
     * SIG_IGN, SIG_DFL, SIG_ERR are no real signals
     *
     * @var array
     * @see setSigHandler()
     */
    static protected $_sigHandlers = array(
        SIGHUP => array('System_Daemon', 'defaultSigHandler'),
        SIGINT => array('System_Daemon', 'defaultSigHandler'),
        SIGQUIT => array('System_Daemon', 'defaultSigHandler'),
        SIGILL => array('System_Daemon', 'defaultSigHandler'),
        SIGTRAP => array('System_Daemon', 'defaultSigHandler'),
        SIGABRT => array('System_Daemon', 'defaultSigHandler'),
        'SIGIOT' => array('System_Daemon', 'defaultSigHandler'),
        SIGBUS => array('System_Daemon', 'defaultSigHandler'),
        SIGFPE => array('System_Daemon', 'defaultSigHandler'),
        SIGUSR1 => array('System_Daemon', 'defaultSigHandler'),
        SIGSEGV => array('System_Daemon', 'defaultSigHandler'),
        SIGUSR2 => array('System_Daemon', 'defaultSigHandler'),
        SIGPIPE => SIG_IGN,
        SIGALRM => array('System_Daemon', 'defaultSigHandler'),
        SIGTERM => array('System_Daemon', 'defaultSigHandler'),
        'SIGSTKFLT' => array('System_Daemon', 'defaultSigHandler'),
        'SIGCLD' => array('System_Daemon', 'defaultSigHandler'),
        'SIGCHLD' => array('System_Daemon', 'defaultSigHandler'),
        SIGCONT => array('System_Daemon', 'defaultSigHandler'),
        SIGTSTP => array('System_Daemon', 'defaultSigHandler'),
        SIGTTIN => array('System_Daemon', 'defaultSigHandler'),
        SIGTTOU => array('System_Daemon', 'defaultSigHandler'),
        SIGURG => array('System_Daemon', 'defaultSigHandler'),
        SIGXCPU => array('System_Daemon', 'defaultSigHandler'),
        SIGXFSZ => array('System_Daemon', 'defaultSigHandler'),
        SIGVTALRM => array('System_Daemon', 'defaultSigHandler'),
        SIGPROF => array('System_Daemon', 'defaultSigHandler'),
        SIGWINCH => array('System_Daemon', 'defaultSigHandler'),
        'SIGPOLL' => array('System_Daemon', 'defaultSigHandler'),
        SIGIO => array('System_Daemon', 'defaultSigHandler'),
        'SIGPWR' => array('System_Daemon', 'defaultSigHandler'),
        'SIGSYS' => array('System_Daemon', 'defaultSigHandler'),
        SIGBABY => array('System_Daemon', 'defaultSigHandler'),
        'SIG_BLOCK' => array('System_Daemon', 'defaultSigHandler'),
        'SIG_UNBLOCK' => array('System_Daemon', 'defaultSigHandler'),
        'SIG_SETMASK' => array('System_Daemon', 'defaultSigHandler'),
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
            include(dirname(__FILE__).'/'.$path);
        } else {
            // Everything else.
            @include($path);
        }
    }


    /**
     * Spawn daemon process.
     *
     * @return boolean
     * @see iterate()
     * @see stop()
     * @see autoload()
     * @see _optionsInit()
     * @see _summon()
     */
    static public function start()
    {
        // Conditionally add loglevel mappings that are not supported in
        // all PHP versions.
        // They will be in string representation and have to be
        // converted & unset
        foreach (self::$_logPhpMapping as $phpConstant => $props) {
            if (!is_numeric($phpConstant)) {
                if (defined($phpConstant)) {
                    self::$_logPhpMapping[constant($phpConstant)] = $props;
                }
                unset(self::$_logPhpMapping[$phpConstant]);
            }
        }
        // Same goes for POSIX signals. Not all Constants are available on
        // all platforms.
        foreach (self::$_sigHandlers as $signal => $handler) {
            if (is_string($signal) || !$signal) {
                if (defined($signal) && ($const = constant($signal))) {
                    self::$_sigHandlers[$const] = $handler;
                }
                unset(self::$_sigHandlers[$signal]);
            }
        }

        // Quickly initialize some defaults like usePEAR
        // by adding the $premature flag
        self::_optionsInit(true);

        if (self::opt('logPhpErrors')) {
            set_error_handler(array('System_Daemon', 'phpErrors'), E_ALL);
        }

        // To run as a part of PEAR
        if (self::opt('usePEAR')) {
            // SPL's autoload will make sure classes are automatically loaded
            if (false === class_exists('PEAR', true)) {
                $msg = 'PEAR not found. Install PEAR or run with option: '.
                    'usePEAR = false';
                trigger_error($msg, E_USER_ERROR);
            }

            if (false === class_exists('PEAR_Exception', true)) {
                $msg = 'PEAR_Exception not found?!';
                trigger_error($msg, E_USER_ERROR);
            }

            if (false === class_exists('System_Daemon_Exception', true)) {
                // PEAR_Exception is OK. PEAR was found already.
                throw new PEAR_Exception('Class System_Daemon_Exception not found');
            }
        }

        // Check the PHP configuration
        if (!defined('SIGHUP')) {
            $msg = 'PHP is compiled without --enable-pcntl directive';
            if (self::opt('usePEAR')) {
                throw new System_Daemon_Exception($msg);
            } else {
                trigger_error($msg, E_USER_ERROR);
            }
        }

        // Check for CLI
        if ((php_sapi_name() !== 'cli')) {
            $msg = 'You can only create daemon from the command line (CLI-mode)';
            if (self::opt('usePEAR')) {
                throw new System_Daemon_Exception($msg);
            } else {
                trigger_error($msg, E_USER_ERROR);
            }
        }

        // Check for POSIX
        if (!function_exists('posix_getpid')) {
            $msg = 'PHP is compiled without --enable-posix directive';
            if (self::opt('usePEAR')) {
                throw new System_Daemon_Exception($msg);
            } else {
                trigger_error($msg, E_USER_ERROR);
            }
        }

        // Enable Garbage Collector (PHP >= 5.3)
        if (function_exists('gc_enable')) {
            gc_enable();
        }

        // Initialize & check variables
        if (false === self::_optionsInit(false)) {
            if (is_object(self::$_optObj) && is_array(self::$_optObj->errors)) {
                foreach (self::$_optObj->errors as $error) {
                    self::notice($error);
                }
            }

            $msg = 'Crucial options are not set. Review log:';
            if (self::opt('usePEAR')) {
                throw new System_Daemon_Exception($msg);
            } else {
                trigger_error($msg, E_USER_ERROR);
            }
        }
        // Become daemon
        self::_summon();

        return true;

    }

    /**
     * Protects your daemon by e.g. clearing statcache. Can optionally
     * be used as a replacement for sleep as well.
     *
     * @param integer $sleepSeconds Optionally put your daemon to rest for X s.
     *
     * @return void
     * @see start()
     * @see stop()
     */
    static public function iterate($sleepSeconds = 0)
    {
        self::_optionObjSetup();
        if ($sleepSeconds !== 0) {
            usleep($sleepSeconds*1000000);
        }

        clearstatcache();

        // Garbage Collection (PHP >= 5.3)
        if (function_exists('gc_collect_cycles')) {
            gc_collect_cycles();
        }

        return true;
    }

    /**
     * Stop daemon process.
     *
     * @return void
     * @see start()
     */
    static public function stop()
    {
        self::info('Stopping {appName}');
        self::_die(false);
    }

    /**
     * Restart daemon process.
     *
     * @return void
     * @see _die()
     */
    static public function restart()
    {
        self::info('Restarting {appName}');
        self::_die(true);
    }

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
            self::notice(
                'Can only overrule on of these signal handlers: %s',
                join(', ', array_keys(self::$_sigHandlers))
            );
            return false;
        }

        // Overwrite on existance
        self::$_sigHandlers[$signal] = $handler;
        return true;
    }

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
    }

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
    }

    /**
     * Shortcut for getOption & setOption
     *
     * @param string $name Option to set or get
     *
     * @return mixed
     */
    static public function opt($name)
    {
        $args = func_get_args();
        if (count($args) > 1) {
            return self::setOption($name, $args[1]);
        } else {
            return self::getOption($name);
        }
    }


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
    }

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
    }

    /**
     * Catches PHP Errors and forwards them to log function
     *
     * @param integer $errno   Level
     * @param string  $errstr  Error
     * @param string  $errfile File
     * @param integer $errline Line
     *
     * @return boolean
     */
    static public function phpErrors ($errno, $errstr, $errfile, $errline)
    {
        // Ignore suppressed errors (prefixed by '@')
        if (error_reporting() == 0) {
            return;
        }

        // Map PHP error level to System_Daemon log level
        if (!isset(self::$_logPhpMapping[$errno][0])) {
            self::warning('Unknown PHP errorno: %s', $errno);
            $phpLvl = self::LOG_ERR;
        } else {
            list($logLvl, $phpLvl) = self::$_logPhpMapping[$errno];
        }

        // Log it
        // No shortcuts this time!
        self::log(
            $logLvl, '[PHP ' . $phpLvl . '] '.$errstr, $errfile, __CLASS__,
            __FUNCTION__, $errline
        );

        return true;
    }

    /**
     * Abbreviate a string. e.g: Kevin van zonneveld -> Kevin van Z...
     *
     * @param string  $str    Data
     * @param integer $cutAt  Where to cut
     * @param string  $suffix Suffix with something?
     *
     * @return string
     */
    static public function abbr($str, $cutAt = 30, $suffix = '...')
    {
        if (strlen($str) <= 30) {
            return $str;
        }

        $canBe = $cutAt - strlen($suffix);

        return substr($str, 0, $canBe). $suffix;
    }

    /**
     * Tries to return the most significant information as a string
     * based on any given argument.
     *
     * @param mixed $arguments Any type of variable
     *
     * @return string
     */
    static public function semantify($arguments)
    {
        if (is_object($arguments)) {
            return get_class($arguments);
        }
        if (!is_array($arguments)) {
            if (!is_numeric($arguments) && !is_bool($arguments)) {
                $arguments = '\''.$arguments.'\'';
            }
            return $arguments;
        }
        $arr = array();
        foreach ($arguments as $key=>$val) {
            if (is_array($val)) {
                $val = json_encode($val);
            } elseif (!is_numeric($val) && !is_bool($val)) {
                $val = '\''.$val.'\'';
            }

            $val = self::abbr($val);

            $arr[] = $key.': '.$val;
        }
        return join(', ', $arr);
    }

    /**
     * Logging shortcut
     *
     * @return boolean
     */
    public static function emerg()
    {
        $arguments = func_get_args(); array_unshift($arguments, __FUNCTION__);
        call_user_func_array(array('System_Daemon', '_ilog'), $arguments);
        return false;
    }

    /**
     * Logging shortcut
     *
     * @return boolean
     */
    public static function crit()
    {
        $arguments = func_get_args(); array_unshift($arguments, __FUNCTION__);
        call_user_func_array(array('System_Daemon', '_ilog'), $arguments);
        return false;
    }

    /**
     * Logging shortcut
     *
     * @return boolean
     */
    public static function err()
    {
        $arguments = func_get_args(); array_unshift($arguments, __FUNCTION__);
        call_user_func_array(array('System_Daemon', '_ilog'), $arguments);
        return false;
    }

    /**
     * Logging shortcut
     *
     * @return boolean
     */
    public static function warning()
    {
        $arguments = func_get_args(); array_unshift($arguments, __FUNCTION__);
        call_user_func_array(array('System_Daemon', '_ilog'), $arguments);
        return false;
    }

    /**
     * Logging shortcut
     *
     * @return boolean
     */
    public static function notice()
    {
        $arguments = func_get_args(); array_unshift($arguments, __FUNCTION__);
        call_user_func_array(array('System_Daemon', '_ilog'), $arguments);
        return true;
    }

    /**
     * Logging shortcut
     *
     * @return boolean
     */
    public static function info()
    {
        $arguments = func_get_args(); array_unshift($arguments, __FUNCTION__);
        call_user_func_array(array('System_Daemon', '_ilog'), $arguments);
        return true;
    }

    /**
     * Logging shortcut
     *
     * @return boolean
     */
    public static function debug()
    {
        $arguments = func_get_args(); array_unshift($arguments, __FUNCTION__);
        call_user_func_array(array('System_Daemon', '_ilog'), $arguments);
        return true;
    }

    /**
     * Internal logging function. Bridge between shortcuts like:
     * err(), warning(), info() and the actual log() function
     *
     * @param mixed $level As string or constant
     * @param mixed $str   Message
     *
     * @return boolean
     */
    protected static function _ilog($level, $str)
    {
        $arguments = func_get_args();
        $level     = $arguments[0];
        $format    = $arguments[1];

        if (is_string($level)) {
            if (false === ($l = array_search($level, self::$_logLevels))) {
                self::log(LOG_EMERG, 'No such loglevel: '. $level);
            } else {
                $level = $l;
            }
        }

        unset($arguments[0]);
        unset($arguments[1]);

        $str = $format;
        if (count($arguments)) {
            foreach ($arguments as $k => $v) {
                $arguments[$k] = self::semantify($v);
            }
            $str = vsprintf($str, $arguments);
        }

        self::_optionObjSetup();
        $str = preg_replace_callback(
            '/\{([^\{\}]+)\}/is',
            array(self::$_optObj, 'replaceVars'),
            $str
        );


        $history  = 2;
        $dbg_bt   = @debug_backtrace();
        $class    = (string)@$dbg_bt[($history-1)]['class'];
        $function = (string)@$dbg_bt[($history-1)]['function'];
        $file     = (string)@$dbg_bt[$history]['file'];
        $line     = (string)@$dbg_bt[$history]['line'];
        return self::log($level, $str, $file, $class, $function, $line);
    }

    /**
     * Almost every deamon requires a log file, this function can
     * facilitate that. Also handles class-generated errors, chooses
     * either PEAR handling or PEAR-independant handling, depending on:
     * self::opt('usePEAR').
     * Also supports PEAR_Log if you referenc to a valid instance of it
     * in self::opt('usePEARLogInstance').
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
    static public function log ($level, $str, $file = false, $class = false,
    $function = false, $line = false) {
        // If verbosity level is not matched, don't do anything
        if (null === self::opt('logVerbosity')
            || false === self::opt('logVerbosity')
        ) {
            // Somebody is calling log before launching daemon..
            // fair enough, but we have to init some log options
            self::_optionsInit(true);
        }
        if (!self::opt('appName')) {
            // Not logging for anything without a name
            return false;
        }

        if ($level > self::opt('logVerbosity')) {
            return true;
        }

        // Make the tail of log massage.
        $log_tail = '';
        if ($level < self::LOG_NOTICE) {
            if (self::opt('logFilePosition')) {
                if (self::opt('logTrimAppDir')) {
                    $file = substr($file, strlen(self::opt('appDir')));
                }

                $log_tail .= ' [f:'.$file.']';
            }
            if (self::opt('logLinePosition')) {
                $log_tail .= ' [l:'.$line.']';
            }
        }

        // Make use of a PEAR_Log() instance
        if (self::opt('usePEARLogInstance') !== false) {
            self::opt('usePEARLogInstance')->log($str . $log_tail, $level);
            return true;
        }

        // Save resources if arguments are passed.
        // But by falling back to debug_backtrace() it still works
        // if someone forgets to pass them.
        if (function_exists('debug_backtrace') && (!$file || !$line)) {
            $dbg_bt   = @debug_backtrace();
            $class    = (isset($dbg_bt[1]['class'])?$dbg_bt[1]['class']:'');
            $function = (isset($dbg_bt[1]['function'])?$dbg_bt[1]['function']:'');
            $file     = $dbg_bt[0]['file'];
            $line     = $dbg_bt[0]['line'];
        }

        // Determine what process the log is originating from and forge a logline
        //$str_ident = '@'.substr(self::_whatIAm(), 0, 1).'-'.posix_getpid();
        $str_date  = '[' . date('M d H:i:s') . ']';
        $str_level = str_pad(self::$_logLevels[$level] . '', 8, ' ', STR_PAD_LEFT);
        $log_line  = $str_date . ' ' . $str_level . ': ' . $str . $log_tail; // $str_ident

        $non_debug      = ($level < self::LOG_DEBUG);
        $log_succeeded = true;
        $log_echoed     = false;

        if (!self::isInBackground() && $non_debug && !$log_echoed) {
            // It's okay to echo if you're running as a foreground process.
            // Maybe the command to write an init.d file was issued.
            // In such a case it's important to echo failures to the
            // STDOUT
            echo $log_line . "\n";
            $log_echoed = true;
            // but still try to also log to file for future reference
        }

        if (!self::opt('logLocation')) {
            throw new System_Daemon_Exception('Either use PEAR Log or specify '.
                'a logLocation');
        }

        // 'Touch' logfile
        if (!file_exists(self::opt('logLocation'))) {
            file_put_contents(self::opt('logLocation'), '');
        }

        // Not writable even after touch? Allowed to echo again!!
        if (!is_writable(self::opt('logLocation'))
            && $non_debug && !$log_echoed
        ) {
            echo $log_line . "\n";
            $log_echoed    = true;
            $log_succeeded = false;
        }

        // Append to logfile
        $f = file_put_contents(
            self::opt('logLocation'),
            $log_line . "\n",
            FILE_APPEND
        );
        if (!$f) {
            $log_succeeded = false;
        }

        // These are pretty serious errors
        if ($level < self::LOG_ERR) {
            // An emergency logentry is reason for the deamon to
            // die immediately
            if ($level === self::LOG_EMERG) {
                self::_die();
            }
        }

        return $log_succeeded;
    }

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
        if (false === self::_optionsInit(false)) {
            return false;
        }

        // Init OS Object
        if (!self::_osObjSetup()) {
            return false;
        }

        // Get daemon properties
        $options = self::getOptions();

        // Try to write init.d
        $res = self::$_osObj->writeAutoRun($options, $overwrite);
        if (false === $res) {
            if (is_array(self::$_osObj->errors)) {
                foreach (self::$_osObj->errors as $error) {
                    self::notice($error);
                }
            }
            return self::warning('Unable to create startup file');
        }

        if ($res === true) {
            self::notice('Startup was already written');
            return true;
        } else {
            self::notice('Startup written to %s', $res);
        }

        return $res;
    }

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
    static public function defaultSigHandler($signo)
    {
        // Must be public or else will throw a
        // fatal error: Call to protected method
        self::debug('Received signal: %s', $signo);

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
            self::debug('Received signal: restart');
            break;
        case SIGCHLD:
            // A child process has died
            self::debug('Received signal: child');
            while (pcntl_wait($status, WNOHANG OR WUNTRACED) > 0) {
                usleep(1000);
            }
            break;
        default:
            // Handle all other signals
            break;
        }
    }

    /**
     * Whether the class is already running in the background
     *
     * @return boolean
     */
    static public function isInBackground()
    {
        return self::$_processIsChild && self::isRunning();
    }

    /**
     * Whether the our daemon is being killed, you might
     * want to include this in your loop
     *
     * @return boolean
     */
    static public function isDying()
    {
        return self::$_isDying;
    }

    /**
     * Check if a previous process with same pidfile was already running
     *
     * @return boolean
     */
    static public function isRunning()
    {
        $appPidLocation = self::opt('appPidLocation');

        if (!file_exists($appPidLocation)) {
            unset($appPidLocation);
            return false;
        }

        $pid = self::fileread($appPidLocation);
        if (!$pid) {
            return false;
        }

        // Ping app
        if (!posix_kill(intval($pid), 0)) {
            // Not responding so unlink pidfile
            @unlink($appPidLocation);
            return self::warning(
                'Orphaned pidfile found and removed: ' .
                '{appPidLocation}. Previous process crashed?'
            );
        }

        return true;
    }



    /**
     * Put the running script in background
     *
     * @return void
     */
    static protected function _summon()
    {
        if (self::opt('usePEARLogInstance')) {
            $logLoc = '(PEAR Log)';
        } else {
            $logLoc = self::opt('logLocation');
        }

        self::notice('Starting {appName} daemon, output in: %s', $logLoc);

        // Allowed?
        if (self::isRunning()) {
            return self::emerg('{appName} daemon is still running. Exiting');
        }

        // Reset Process Information
        self::$_safeMode       = !!@ini_get('safe_mode');
        self::$_processId      = 0;
        self::$_processIsChild = false;

        // Fork process!
        if (!self::_fork()) {
            return self::emerg('Unable to fork');
        }

        // Additional PID succeeded check
        if (!is_numeric(self::$_processId) || self::$_processId < 1) {
            return self::emerg('No valid pid: %s', self::$_processId);
        }

        // Change umask
        @umask(0);

        // Write pidfile
        $p = self::_writePid(self::opt('appPidLocation'), self::$_processId);
        if (false === $p) {
            return self::emerg('Unable to write pid file {appPidLocation}');
        }

        // Change identity. maybe
        $c = self::_changeIdentity(
            self::opt('appRunAsGID'),
            self::opt('appRunAsUID')
        );
        if (false === $c) {
            self::crit('Unable to change identity');
            if (self::opt('appDieOnIdentityCrisis')) {
                self::emerg('Cannot continue after this');
            }
        }

        // Important for daemons
        // See http://www.php.net/manual/en/function.pcntl-signal.php
        declare(ticks = 1);

        // Setup signal handlers
        // Handlers for individual signals can be overrulled with
        // setSigHandler()
        foreach (self::$_sigHandlers as $signal => $handler) {
            if (!is_callable($handler) && $handler != SIG_IGN && $handler != SIG_DFL) {
                return self::emerg(
                    'You want to assign signal %s to handler %s but ' .
                    'it\'s not callable',
                    $signal,
                    $handler
                );
            } else if (!pcntl_signal($signal, $handler)) {
                return self::emerg(
                    'Unable to reroute signal handler: %s',
                    $signal
                );
            }
        }

        // Change dir
        @chdir(self::opt('appDir'));

        return true;
    }

    /**
     * Determine whether pidfilelocation is valid
     *
     * @param string  $pidFilePath Pid location
     * @param boolean $log         Allow this function to log directly on error
     *
     * @return boolean
     */
    static protected function _isValidPidLocation($pidFilePath, $log = true)
    {
        if (empty($pidFilePath)) {
            return self::err(
                '{appName} daemon encountered an empty appPidLocation'
            );
        }

        $pidDirPath = dirname($pidFilePath);
        $parts      = explode('/', $pidDirPath);
        if (count($parts) <= 3 || end($parts) != self::opt('appName')) {
            // like: /var/run/x.pid
            return self::err(
                'Since version 0.6.3, the pidfile needs to be ' .
                'in it\'s own subdirectory like: %s/{appName}/{appName}.pid'
            );
        }

        return true;
    }

    /**
     * Creates pid dir and writes process id to pid file
     *
     * @param string  $pidFilePath PID File path
     * @param integer $pid         PID
     *
     * @return boolean
     */
    static protected function _writePid($pidFilePath = null, $pid = null)
    {
        if (empty($pid)) {
            return self::err('{appName} daemon encountered an empty PID');
        }

        if (!self::_isValidPidLocation($pidFilePath, true)) {
            return false;
        }

        $pidDirPath = dirname($pidFilePath);

        if (!self::_mkdirr($pidDirPath, 0755)) {
            return self::err('Unable to create directory: %s', $pidDirPath);
        }

        if (!file_put_contents($pidFilePath, $pid)) {
            return self::err('Unable to write pidfile: %s', $pidFilePath);
        }

        if (!chmod($pidFilePath, 0644)) {
            return self::err('Unable to chmod pidfile: %s', $pidFilePath);;
        }

        return true;
    }

    /**
     * Read a file. file_get_contents() leaks memory! (#18031 for more info)
     *
     * @param string $filepath
     *
     * @return string
     */
    static public function fileread ($filepath) {
        $f = fopen($filepath, 'r');
        if (!$f) {
            return false;
        }
        $data = fread($f, filesize($filepath));
        fclose($f);
        return $data;
    }

    /**
     * Recursive alternative to mkdir
     *
     * @param string  $dirPath Directory to create
     * @param integer $mode    Umask
     *
     * @return boolean
     */
    static protected function _mkdirr($dirPath, $mode)
    {
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
    static protected function _changeIdentity($gid = 0, $uid = 0)
    {
        // What files need to be chowned?
        $chownFiles = array();
        if (self::_isValidPidLocation(self::opt('appPidLocation'), true)) {
            $chownFiles[] = dirname(self::opt('appPidLocation'));
        }
        $chownFiles[] = self::opt('appPidLocation');
        if (!is_object(self::opt('usePEARLogInstance'))) {
            $chownFiles[] = self::opt('logLocation');
        }

        // Chown pid- & log file
        // We have to change owner in case of identity change.
        // This way we can modify the files even after we're not root anymore
        foreach ($chownFiles as $filePath) {
            // Change File GID
            $doGid = (filegroup($filePath) != $gid ? $gid : false);
            if (false !== $doGid && !@chgrp($filePath, intval($gid))) {
                return self::err(
                    'Unable to change group of file %s to %s',
                    $filePath,
                    $gid
                );
            }

            // Change File UID
            $doUid = (fileowner($filePath) != $uid ? $uid : false);
            if (false !== $doUid && !@chown($filePath, intval($uid))) {
                return self::err(
                    'Unable to change user of file %s to %s',
                    $filePath,
                    $uid
                );
            }

			// Export correct homedir
			if (($info = posix_getpwuid($uid)) && is_dir($info['dir'])) {
				system('export HOME="' . $info['dir'] . '"');
			}
        }

        // Change Process GID
        $doGid = (posix_getgid() !== $gid ? $gid : false);
        if (false !== $doGid && !@posix_setgid($gid)) {
            return self::err('Unable to change group of process to %s', $gid);
        }

        // Change Process UID
        $doUid = (posix_getuid() !== $uid ? $uid : false);
        if (false !== $doUid && !@posix_setuid($uid)) {
            return self::err('Unable to change user of process to %s', $uid);
        }

        $group = posix_getgrgid($gid);
        $user  = posix_getpwuid($uid);

        return self::info(
            'Changed identify to %s:%s',
            $group['name'],
            $user['name']
        );
    }

    /**
     * Fork process and kill parent process, the heart of the 'daemonization'
     *
     * @return boolean
     */
    static protected function _fork()
    {
        self::debug('forking {appName} daemon');
        $pid = pcntl_fork();
        if ($pid === -1) {
            // Error
            return self::warning('Process could not be forked');
        } else if ($pid) {
            // Parent
            self::debug('Ending {appName} parent process');
            // Die without attracting attention
            exit();
        } else {
            // Child
            self::$_processIsChild = true;
            self::$_isDying        = false;
            self::$_processId      = posix_getpid();
            return true;
        }
    }

    /**
     * Return what the current process is: child or parent
     *
     * @return string
     */
    static protected function _whatIAm()
    {
        return (self::isInBackground() ? 'child' : 'parent');
    }

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
        if (self::isDying()) {
            return null;
        }

        self::$_isDying = true;
        // Following caused a bug if pid couldn't be written because of
        // privileges
        // || !file_exists(self::opt('appPidLocation'))
        if (!self::isInBackground()) {
            self::info(
                'Process was not daemonized yet, ' .
                'just halting current process'
            );
            die();
        }

        $pid = file_get_contents(
            System_Daemon::getOption('appPidLocation')
        );
        @unlink(self::opt('appPidLocation'));

        if ($restart) {
            // So instead we should:
            die(exec(join(' ', $GLOBALS['argv']) . ' > /dev/null &'));
        } else {
            passthru('kill -9 ' . $pid);
            die();
        }
    }


    /**
     * Sets up OS instance
     *
     * @return boolean
     */
    static protected function _osObjSetup()
    {
        // Create Option Object if nescessary
        if (!self::$_osObj) {
            self::$_osObj = System_Daemon_OS::factory();
        }

        // Still false? This was an error!
        if (!self::$_osObj) {
            return self::emerg('Unable to setup OS object');
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
        if (!self::$_optObj) {
            self::$_optObj = new System_Daemon_Options(self::$_optionDefinitions);
        }

        // Still false? This was an error!
        if (!self::$_optObj) {
            return self::emerg('Unable to setup Options object. ');
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
    }
}
