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
     * Holds drivers for all the different Operating Systems
     *
     * @var array
     */
    private $_driversAll = array();
    
    /**
     * Holds drivers for all the different Installed Operating Systems
     *
     * @var array
     */
    private $_driversValid = array();
    
    /**
     * Holds driver for the Most specific Operating Systems found
     *
     * @var array
     */
    private $_driverUse = array();
    

    
    /**
     * Constructor
     * Loads all the drivers and returns the one for the most specifc OS
     */
    public function __construct() 
    {
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
            $this->_driversAll[$class] = &new $class;            
        }
        
        // What OSes are valid for this system?
        // e.g. Debian makes Linux valid as well
        foreach ($this->_driversAll as $class=>$obj) {
            // Save in Installed container
            if (call_user_func(array($obj, "isInstalled"))) {
                $this->_driversValid[$class] = $obj;         
            }
        }
        
        // What's the most specific OS?
        // e.g. Ubuntu > Debian > Linux    
        $usename          = $this->_mostSpecific($this->_driversValid);
        $this->_driverUse = $this->_driversValid[$usename];        
    }

    public function &getSpecific() {
        return $this->_driverUse;
    }
    
    
        
    
    
    
    
    
    
    
    

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
        $weights = array_map(array($this, "_getAncestorCount"), $classes);
        arsort($weights);        
        return reset(array_keys($weights));
    }
    
    /**
     * Get the total parent count of a class
     *
     * @param string $class
     * 
     * @return integer
     */
    private function _getAncestorCount ($class) {
        return count($this->_getAncestors($class));        
    }
    
    
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
    }    
    
}//end class
?>