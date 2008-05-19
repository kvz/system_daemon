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

/**
 * Operating System focussed functionality.
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
class System_Daemon_OS extends System_Daemon
{

    /**
     * Operating systems and versions are based on the existence and
     * the information found in these files.
     * The order is important because Ubuntu has also has a Debian file
     * for compatibility purposes. So in this case, scan for most specific
     * first.
     *
     * @var array
     */    
    static public $osVersionFiles = array(
        "Mandrake"=>"/etc/mandrake-release",
        "SuSE"=>"/etc/SuSE-release",
        "RedHat"=>"/etc/redhat-release",
        "Ubuntu"=>"/etc/lsb-release",
        "Debian"=>"/etc/debian_version"
    );
    
    /**
     * Array that holds the properties of the parent
     * daemon. Can be inheritted, or overridden by using
     * the $properties parameter of the constructor
     *
     * @var array
     */
    static protected $daemonProperties = array();
    
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
     */
    private function __construct() 
    {
        
    }    
    
    
    
    /**
     * Decide what facility to log to.
     *  
     * @param integer $level    What function the log record is from
     * @param string  $str      The log record
     * @param string  $file     What code file the log record is from
     * @param string  $class    What class the log record is from
     * @param string  $function What function the log record is from
     * @param integer $line     What code line the log record is from
     *
     * @throws System_Daemon_OS_Exception  
     * @return void
     */
    static public function log($level, $str, $file = false, $class = false, 
        $function = false, $line = false)
    {
        if (class_exists("System_Daemon")) {
            // preferably let parent System_Daemon class handle
            // any errors. throws exceptions as well, but gives
            // a single & independent point of log flow control.
            parent::log($level, $str, $file, $class, $function, $line);
        } elseif ($level < parent::LOG_NOTICE) {
            // Only make exceptions in case of errors
            if (class_exists("System_Daemon_OS_Exception", true) === false) {
                // Own exception
                throw new System_Daemon_OS_Exception($log_line);
            } elseif (class_exists("PEAR_Exception", true) === false) {
                // PEAR exception if not standalone
                throw new PEAR_Exception($log_line);
            } elseif (class_exists("Exception", true) === false) {
                // General exception
                throw new Exception($log_line);
            } else {
                // This should never happen when running in 'PEAR-mode'
                trigger_error("Panic: No valid log facility available!\n", 
                    E_USER_ERROR);
            }                     
        }
    }//end log()   
        
    /**
     * Sets daemon specific properties
     *  
     * @param array $properties Contains the daemon properties
     * 
     * @return array
     */       
    static public function setProperties($properties = false) 
    {
        if (!is_array($properties) || !count($properties)) {
            self::log(parent::LOG_WARNING, "No properties to ".
                "forge init.d script", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false; 
        }
                
        // Tests
        $required_props = array("appName", "appDescription", "appDir", 
            "authorName", "authorEmail");
        
        // check if all required properties are available
        foreach ($required_props as $required_prop) {
            if (!isset($required_props[$required_prop]) 
                || !$required_props[$required_prop]) {
                self::log(parent::LOG_WARNING, "Cannot forge an ".
                    "init.d script without a valid ".
                    "daemon property: ".$required_prop, 
                    __FILE__, __CLASS__, __FUNCTION__, __LINE__);
                return false;
            }
        
            // addslashes
            $required_props[$required_prop] = 
                addslashes($required_props[$required_prop]);
        }
        
        // override
        self::$daemonProperties = $properties;
        return true;
        
    } // end setProperties
        
    /**
     * Returns an array(main, distro, version) of the OS it's executed on
     *
     * @return array
     */
    static public function determine()
    {
        // this will not change during 1 run, so just cache the result
        if (!isset(self::$_intFunctionCache[__FUNCTION__])) {
            if (strtoupper(substr(PHP_OS, 0, 3)) === 'WIN') {
                $main   = "Windows";
                $distro = PHP_OS;
            } else if (stristr(PHP_OS, "Darwin")) {
                $main   = "BSD";
                $distro = "Mac OSX";
            } else if (stristr(PHP_OS, "Linux")) {
                $main = php_uname('s');
                foreach (self::$osVersionFiles as $distro=>$osv_file) {
                    if (file_exists($osv_file)) {
                        $version = trim(file_get_contents($osv_file));
                        break;
                    }
                }
            } else {
                return false;
            }

            self::$_intFunctionCache[__FUNCTION__] = compact("main", "distro", 
                "version");
        }

        return self::$_intFunctionCache[__FUNCTION__];
    }//end determine()  
    
    /**
     * Writes an: 'init.d' script on the filesystem
     *
     * @param bolean $overwrite May the existing init.d file be overwritten?
     * 
     * @return mixed boolean on failure, string on success
     * @see initDLocation()
     * @see initDForge()
     */
    static public function initDWrite($overwrite = false)
    {
        // up to date filesystem information
        clearstatcache();
        
        // collect init.d path
        $initd_location = self::initDLocation();
        if (!$initd_location) {
            // explaining errors should have been generated by 
            // System_Daemon_OS::initDLocation() 
            // already
            return false;
        }
        
        // collect init.d body
        $initd_body = self::initDForge();
        if (!$initd_body) {
            // explaining errors should have been generated by osInitDForge() 
            // already
            return false;
        }
        
        // as many safety checks as possible
        if (!$overwrite && file_exists(($initd_location))) {
            self::log(parent::LOG_WARNING, "init.d script already exists", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;
        } 
        if (!is_dir($dir = dirname($initd_location))) {
            self::log(parent::LOG_WARNING, "init.d directory: '".
                $dir."' does not ".
                "exist. Can this be a correct path?", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }
        if (!is_writable($dir = dirname($initd_location))) {
            self::log(parent::LOG_WARNING, "init.d directory: '".
                $dir."' cannot be ".
                "written to. Check the permissions", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }
        
        if (!file_put_contents($initd_location, $initd_body)) {
            self::log(parent::LOG_WARNING, "init.d file: '".
                $initd_location."' cannot be ".
                "written to. Check the permissions", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }
        
        if (!chmod($initd_location, 0777)) {
            self::log(parent::LOG_WARNING, "init.d file: '".
                $initd_location."' cannot be ".
                "chmodded. Check the permissions", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;
        } 
        
        return $initd_location;
    }//end System_Daemon_OS::initDWrite() 
    
    /**
     * Returns an: 'init.d' script path as a string. For now only Debian & Ubuntu
     * Results are cached because they will not change during one run.
     *
     * @return mixed boolean on failure, string on success
     * @see $_intFunctionCache
     * @see determine()
     */
    static public function initDLocation()
    {
        // this will not change during 1 run, so just cache the result
        if (!isset(self::$_intFunctionCache[__FUNCTION__])) {
            $initd_location = false;

            // daemon properties
            $properties = self::$daemonProperties;
                        
            // collect OS information
            list($main, $distro, $version) = array_values(self::determine());
            
            // where to collect the skeleton (template) for our init.d script
            switch (strtolower($distro)){
            case "debian":
            case "ubuntu":
                // here it is for debian systems
                $initd_location = "/etc/init.d/".$properties["appName"];
                break;
            default:
                // not supported yet
                self::log(parent::LOG_WARNING, "skeleton retrieval for OS: ".
                    $distro.
                    " currently not supported ", 
                    __FILE__, __CLASS__, __FUNCTION__, __LINE__);
                return false;
            }
            
            self::$_intFunctionCache[__FUNCTION__] = $initd_location;
        }
        
        return self::$_intFunctionCache[__FUNCTION__];
    }//end initDLocation()
    
    /**
     * Returns an: 'init.d' script as a string. for now only Debian & Ubuntu
     * 
     * @throws System_Daemon_Exception
     * @return mixed boolean on failure, string on success
     */
    static public function initDForge( )
    {
        // initialize & check variables
        $skeleton_filepath = false;
        
        // daemon properties
        $properties = self::$daemonProperties;
                
        // check path
        $daemon_filepath = $properties["appDir"]."/".$properties["appExecutable"];
        if (!file_exists($daemon_filepath)) {
            self::log(parent::LOG_WARNING, 
                "unable to forge startup script for non existing ".
                "daemon_filepath: ".$daemon_filepath.", try setting a valid ".
                "appDir or appExecutable", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }
        
        // daemon file needs to be executable 
        if (!is_executable($daemon_filepath)) {
            self::log(parent::LOG_WARNING, 
                "unable to forge startup script. ".
                "daemon_filepath: ".$daemon_filepath.", needs to be executable ".
                "first", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }
        
        // collect OS information
        list($main, $distro, $version) = array_values(self::determine());

        // where to collect the skeleton (template) for our init.d script
        switch (strtolower($distro)){
        case "debian":
        case "ubuntu":
            // here it is for debian based systems
            $skeleton_filepath = "/etc/init.d/skeleton";
            break;
        default:
            // not supported yet
            self::log(parent::LOG_WARNING, 
                "skeleton retrieval for OS: ".$distro.
                " currently not supported ", 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;
            break;
        }

        // open skeleton
        if (!$skeleton_filepath || !file_exists($skeleton_filepath)) {
            self::log(parent::LOG_WARNING, 
                "skeleton file for OS: ".$distro." not found at: ".
                $skeleton_filepath, 
                __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }
        
        if ($skeleton = file_get_contents($skeleton_filepath)) {
            // skeleton opened, set replace vars
            switch (strtolower($distro)){
            case "debian":
            case "ubuntu":                
                $replace = array(
                    "Foo Bar" => $properties["authorName"],
                    "foobar@baz.org" => $properties["authorEmail"],
                    "daemonexecutablename" => $properties["appName"],
                    "Example" => $properties["appName"],
                    "skeleton" => $properties["appName"],
                    "/usr/sbin/\$NAME" => $daemon_filepath,
                    "Description of the service"=> $properties["appDescription"],
                    " --name \$NAME" => "",
                    "--options args" => "",
                    "# Please remove the \"Author\" ".
                        "lines above and replace them" => "",
                    "# with your own name if you copy and modify this script." => ""
                );
                break;
            default:
                // not supported yet
                self::log(parent::LOG_WARNING, 
                    "skeleton modification for OS: ".$distro.
                    " currently not supported ", 
                    __FILE__, __CLASS__, __FUNCTION__, __LINE__);
                return false;
                break;
            }

            // replace skeleton placeholders with actual daemon information
            $skeleton = str_replace(array_keys($replace), 
                array_values($replace), 
                $skeleton);

            // return the forged init.d script as a string
            return $skeleton;
        }
    }//end initDForge()
}//end class
?>