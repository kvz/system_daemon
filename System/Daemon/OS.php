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
class System_Daemon_OS
{

    /**
     * Holds errors
     *
     * @var array
     */
    public $errors = array();
    
    
    
    /**
     * Array that holds the properties of the parent
     * daemon. Can be inheritted, or overridden by using
     * the $properties parameter of the constructor
     *
     * @var array
     */
    protected $daemonProperties = array();
    
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
     * Hold OS information
     *
     * @var array
     */
    private $_osDetails = array();
        
    
    
    /**
     * Constructor
     * Only run by instantiated OS Drivers
     */
    public function __construct() 
    {
        // Up to date filesystem information
        clearstatcache();

        // Get ancestors
        $ancs = System_Daemon_OS::_getAncestors($this);
        foreach ($ancs as $i=>$anc) {
            $ancs[$i] = System_Daemon_OS::_getShortHand($anc);
        }        
        
        // Set OS Details
        $this->_osDetails["shorthand"] = $this->_getShortHand(get_class($this));
        $this->_osDetails["ancestors"] = $ancs;
    }

    /**
     * Loads all the drivers and returns the one for the most specifc OS
     *
     * @return unknown
     */
    public function &factory()
    {
        
        $drivers      = array();
        $driversValid = array();
        $class_prefix = "System_Daemon_OS_";
        
        // Load all drivers
        $driver_dir = realpath(dirname(__FILE__)."/OS");
        foreach (glob($driver_dir."/*.php") as $driver_path) {
            // Set names
            $driver = basename($driver_path, ".php");
            $class  = $class_prefix.$driver;
            
            // Only do this for real drivers
            if ($driver == "Exception" || !is_file($driver_path)) {
                continue;
            }
            
            // Let SPL include & load the driver or Report errors
            if (!class_exists($class, true)) {
                $this->errors[] = "Class ".$class." does not exist";
                return false;
            }

            // Save in drivers array
            $drivers[$class] = new $class;            
        }
        
        
        // What OSes are valid for this system?
        // e.g. Debian makes Linux valid as well
        foreach ($drivers as $class=>$obj) {
            // Save in Installed container
            if (call_user_func(array($obj, "isInstalled"))) {
                $driversValid[$class] = $obj;         
            }
        }
        
        // What's the most specific OS?
        // e.g. Ubuntu > Debian > Linux    
        $use_name = System_Daemon_OS::_mostSpecific($driversValid);
        $obj      = $driversValid[$use_name];
                        
        return $obj;
    }//end &factory()
        
    /**
     * Determines wether the system is compatible with this OS
     *
     * @return boolean
     */    
    public function isInstalled() 
    {
        $this->errors[] = "Not implemented for OS";
        return false;
    }//end isInstalled
    
    public function getDetails()
    {
        return $this->_osDetails;
    }//end getDetails
    
    public function getAutoRunPath() 
    {
        $this->errors[] = "Not implemented for OS";
        return false;
    }//end getAutoRunPath
    
    public function getAutoRunScript()
    {
        $this->errors[] = "Not implemented for OS";
        return false;
    }//end getAutoRunScript()
    
    /**
     * Writes an: 'init.d' script on the filesystem
     *
     */
    public function writeAutoRun()
    {
        $this->errors[] = "Not implemented for OS";
        return false;
    }//end writeAutoRun() 
    
    
    
    /**
     * Sets daemon specific properties
     *  
     * @param array $properties Contains the daemon properties
     * 
     * @return array
     */       
    private function _testAutoRunProperties($properties = false) 
    {
        if (!is_array($properties) || !count($properties)) {
            $this->errors[] = "No properties to ".
                "forge init.d script";
            return false; 
        }
                
        // Tests
        $required_props = array("appName", "appExecutable", 
            "appDescription", "appDir", 
            "authorName", "authorEmail");
        
        // Check if all required properties are available
        $success = true;
        foreach ($required_props as $required_prop) {
            if (!isset($properties[$required_prop])) {
                $this->errors[] = "Cannot forge an ".
                    "init.d script without a valid ".
                    "daemon property: ".$required_prop;
                $success        = false;
                continue;
            }            
        }
        
        // Check path
        $daemon_filepath = $properties["appDir"]."/".$properties["appExecutable"];
        if (!file_exists($daemon_filepath)) {
            $this->errors[] = "unable to forge startup script for non existing ".
                "daemon_filepath: ".$daemon_filepath.", try setting a valid ".
                "appDir or appExecutable";
            $success = false;
        }
        
        // Daemon file needs to be executable 
        if (!is_executable($daemon_filepath)) {
            $this->errors[] = "unable to forge startup script. ".
                "daemon_filepath: ".$daemon_filepath.", needs to be executable ".
                "first";
            $success = false;
        }
        
        return $success;
        
    } //end _testAutoRunProperties    
    
    
    /**
     * Determines how specific an operating system is.
     * e.g. Ubuntu is more specific than Debian is more 
     * specific than Linux is more specfic than Common.
     * Determined based on class hierarchy.
     *
     * @param array $classes
     * 
     * @return string
     */
    private function _mostSpecific($classes) {
        $weights = array_map(array("System_Daemon_OS", "_getAncestorCount"), $classes);
        arsort($weights);        
        return reset(array_keys($weights));
    }//end _mostSpecific
    
    /**
     * Extracts last part of a classname. e.g. System_Daemon_OS_Ubuntu -> Ubuntu
     *
     * @param unknown_type $class
     * @return unknown
     */
    private function _getShortHand($class) {
        if (!is_string($class) || ! $class ) {
            return false;
        }
        $parts = explode("_", $class);
        return end($parts);
    } //end _getShortHand
    
    /**
     * Get the total parent count of a class
     *
     * @param string $class
     * 
     * @return integer
     */
    private function _getAncestorCount ($class) {
        return count(System_Daemon_OS::_getAncestors($class));        
    }//end _getAncestorCount
    
    /**
     * Get an array of parent classes
     *
     * @param string $class
     * 
     * @return array
     */
    private function _getAncestors($class) {
        $classes     = array();
        while($class = get_parent_class($class)) { 
            $classes[] = $class; 
        }
        return $classes;
    }//end _getAncestors
    
}//end class
?>