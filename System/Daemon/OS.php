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
 * @version   SVN: Release: $Id: OS.php 150 2008-09-05 22:06:05Z kevin $
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
 * @version   SVN: Release: $Id: OS.php 150 2008-09-05 22:06:05Z kevin $
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
     * Template path
     *
     * @var string
     */
    protected $_autoRunTemplatePath = "";

    /**
     * Replace the following keys with values to convert a template into
     * a read autorun script
     *
     * @var array
     */
    protected $_autoRunTemplateReplace = array();

    /**
     * Path of init.d scripts
     *
     * @var string
     */
    protected $_autoRunDir;

    /**
     * Hold OS information
     *
     * @var array
     */
    protected $_osDetails = array();



    /**
     * Constructor
     * Only ran by instantiated OS Drivers
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
     * @param mixed   $force_os boolean or string when you want to enforce an OS
     * for testing purposes. CAN BE VERY DANGEROUS IF WRONG OS IS SPECIFIED!
     * Will otherwise autodetect OS.
     * @param boolean $retried  used internally to find out wether we are retrying
     *
     * @return object
     */
    public function &factory($force_os = false, $retried = false)
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

        // Determine which one to use
        if ($force_os !== false) {
            // Let's use the Forced OS. This could be dangerous
            $use_name = $class_prefix.$force_os;
        } else {
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
        }

        // If forced driver wasn't found, retry to autodetect it
        if (!isset($drivers[$use_name])) {
            // Make sure we don't build a loop
            if (!$retried) {
                $obj           = System_Daemon_OS::factory(false, true);
                $obj->errors[] = "Unable to use driver: ".$force_os." falling ".
                    "back to autodetection.";
            } else {
                $obj = false;
            }
        } else {
            $obj = $drivers[$use_name];
        }

        return $obj;
    }



    /**
     * Determines wether the system is compatible with this OS
     *
     * @return boolean
     */
    public function isInstalled()
    {
        $this->errors[] = "Not implemented for OS";
        return false;
    }

    /**
     * Returns array with all the specific details of the loaded OS
     *
     * @return array
     */
    public function getDetails()
    {
        return $this->_osDetails;
    }

    /**
     * Returns a template path to base the autuRun script on.
     * Uses $autoRunTemplatePath if possible.
     *
     * @param array $properties Additional properties
     *
     * @return unknown
     * @see autoRunTemplatePath
     */
    public function getAutoRunTemplatePath($properties = array())
    {
        $path = $this->_autoRunTemplatePath;

        if (!empty($properties['runTemplateLocation'])) {
            $path = $properties['runTemplateLocation'];
        }

        if (!$path) {
            $this->errors[] = "No autoRunTemplatePath found";
            return false;
        }

        // Replace variable: #datadir#
        // with actual package datadir
        // this enables predefined templates for e.g. redhat & bsd
        if (false !== strpos($path, '#datadir#')) {
            $dataDir = $this->getDataDir();
            if (false === $dataDir) {
                return false;
            }
            $path = str_replace('#datadir#', $dataDir, $path);
        }

        return $path;
    }

    /**
     * Returns the directory where data is stored (like init.d templates)
     * Could be PEAR's data directory but could also be a local one.
     *
     * @return string
     */
    public function getDataDir()
    {
        $tried_dirs = array();

        if (class_exists('PEAR_Config', true)) {
            $config = PEAR_Config::singleton();
            if (PEAR::isError($config)) {
                $this->errors[] = $config->getMessage();
                return false;
            }

            $try_dir = realpath(
                $config->get('data_dir').
                '/System_Daemon/data'
            );
            if (!is_dir($try_dir)) {
                $tried_dirs[] = $try_dir;
            } else {
                $dir = $try_dir;
            }
        }

        if (!$dir) {
            $try_dir = realpath(dirname(__FILE__).'/../../data');
            if (!is_dir($try_dir)) {
                $tried_dirs[] = $try_dir;
            } else {
                $dir = $try_dir;
            }
        }

        if (!$dir) {
            $this->errors[] = 'No data dir found in either: '.
            implode(' or ', $tried_dirs);
            return false;
        }

        return $dir;
    }

    /**
     * Returns OS specific path to autoRun file
     *
     * @param string $appName Unix-proof name of daemon
     *
     * @return string
     */
    public function getAutoRunPath($appName)
    {
        if (empty($this->_autoRunDir)) {
            $this->errors[] = "autoRunDir is not set";
            return false;
        }

        $path = $this->_autoRunDir."/".$appName;

        // Path exists
        if (!is_dir($dir = dirname($path))) {
            $this->errors[] = "Directory: '".$dir."' does not exist. ".
                "How can this be a correct path?";
            return false;
        }

        // Is writable?
        if (!self::isWritable($dir)) {
            $this->errors[] = "Directory: '".$dir."' is not writable. ".
                "Maybe run as root (now: " . getmyuid() . ")?";
            return false;
        }

        return $path;
    }
    
    /**
     * A 'better' is_writable. Taken from PHP.NET comments:
     * http://nl.php.net/manual/en/function.is-writable.php#73596
     * Will work in despite of Windows ACLs bug
     * NOTE: use a trailing slash for folders!!!
     * see http://bugs.php.net/bug.php?id=27609
     * see http://bugs.php.net/bug.php?id=30931
     *
     * @param string $path Path to test
     * 
     * @return boolean
     */
    public static function isWritable($path)
    {
        if ($path{strlen($path)-1} === '/') {
            //// recursively return a temporary file path
            return self::isWritable($path.uniqid(mt_rand()).'.tmp');
        } else if (is_dir($path)) {
            return self::isWritable($path.'/'.uniqid(mt_rand()).'.tmp');
        }
        // check tmp file for read/write capabilities
        if (($rm = file_exists($path))) {
            $f = fopen($path, 'a');
        } else {
            $f = fopen($path, 'w');
        }
        if ($f === false) {
            print_r($path);
            return false;
        }
        @fclose($f);
        if (!$rm) {
            unlink($path);
        }
        return true;
    }

    /**
     * Returns a template to base the autuRun script on.
     * Uses $autoRunTemplatePath if possible.
     *
     * @param array $properties Contains the daemon properties
     *
     * @return unknown
     * @see autoRunTemplatePath
     */
    public function getAutoRunTemplate($properties)
    {
        if (($path = $this->getAutoRunTemplatePath($properties)) === false) {
            return false;
        }

        if (!file_exists($path)) {
            $this->errors[] = "autoRunTemplatePath: ".
            $path." does not exist";
            return false;
        }

        return file_get_contents($path);
    }

    /**
     * Uses properties to enrich the autuRun Template
     *
     * @param array $properties Contains the daemon properties
     *
     * @return mixed string or boolean on failure
     */
    public function getAutoRunScript($properties)
    {

        // All data in place?
        if (($template = $this->getAutoRunTemplate($properties)) === false) {
            return false;
        }
        if (!$this->_autoRunTemplateReplace
            || !is_array($this->_autoRunTemplateReplace)
            || !count($this->_autoRunTemplateReplace)
        ) {
            $this->errors[] = "No autoRunTemplateReplace found";
            return false;
        }

        // Replace System specific keywords with Universal placeholder keywords
        $script = str_replace(
            array_keys($this->_autoRunTemplateReplace),
            array_values($this->_autoRunTemplateReplace),
            $template
        );

        // Replace Universal placeholder keywords with Daemon specific properties
        if (!preg_match_all('/(\{PROPERTIES([^\}]+)\})/is', $script, $r)) {
            $this->errors[] = "No PROPERTIES found in autoRun template";
            return false;
        }

        $placeholders = $r[1];
        array_unique($placeholders);
        foreach ($placeholders as $placeholder) {
            // Get var
            $var = str_replace(array("{PROPERTIES.", "}"), "", $placeholder);

            // Replace placeholder with actual daemon property
            $script = str_replace($placeholder, $properties[$var], $script);
        }

        return $script;
    }

    /**
     * Writes an: 'init.d' script on the filesystem
     * combining
     *
     * @param array   $properties Contains the daemon properties
     * @param boolean $overwrite  Wether to overwrite when the file exists
     *
     * @return mixed string or boolean on failure
     * @see getAutoRunScript()
     * @see getAutoRunPath()
     */
    public function writeAutoRun($properties, $overwrite = false)
    {
        // Check properties
        if ($this->_testAutoRunProperties($properties) === false) {
            // Explaining errors should have been generated by
            // previous function already
            return false;
        }

        // Get script body
        if (($body = $this->getAutoRunScript($properties)) === false) {
            // Explaining errors should have been generated by
            // previous function already
            return false;
        }

        // Get script path
        if (($path = $this->getAutoRunPath($properties["appName"])) === false) {
            // Explaining errors should have been generated by
            // previous function already
            return false;
        }

        // Overwrite?
        if (file_exists($path) && !$overwrite) {
            return true;
        }

        // Write
        if (!file_put_contents($path, $body)) {
            $this->errors[] =  "startup file: '".
            $path."' cannot be ".
                "written to. Check the permissions";
            return false;
        }

        // Chmod
        if (!chmod($path, 0777)) {
            $this->errors[] =  "startup file: '".
            $path."' cannot be ".
                "chmodded. Check the permissions";
            return false;
        }


        return $path;
    }



    /**
     * Sets daemon specific properties
     *
     * @param array $properties Contains the daemon properties
     *
     * @return array
     */
    protected function _testAutoRunProperties($properties = false)
    {
        $required_props = array(
            "appName",
            "appExecutable",
            "appDescription", 
            "appDir",
            "authorName",
            "authorEmail"
        );

        // Valid array?
        if (!is_array($properties) || !count($properties)) {
            $this->errors[] = "No properties to ".
                "forge init.d script";
            return false;
        }

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

        // Path to daemon
        $daemon_filepath = $properties["appDir"] . "/" .
            $properties["appExecutable"];

        // Path to daemon exists?
        if (!file_exists($daemon_filepath)) {
            $this->errors[] = "unable to forge startup script for non existing ".
                "daemon_filepath: ".$daemon_filepath.", try setting a valid ".
                "appDir or appExecutable";
            $success        = false;
        }

        // Path to daemon is executable?
        if (!is_executable($daemon_filepath)) {
            $this->errors[] = "unable to forge startup script. ".
                "daemon_filepath: ".$daemon_filepath.", needs to be executable ".
                "first";
            $success        = false;
        }

        return $success;

    }

    /**
     * Determines how specific an operating system is.
     * e.g. Ubuntu is more specific than Debian is more
     * specific than Linux is more specfic than Common.
     * Determined based on class hierarchy.
     *
     * @param array $classes Array with keys with classnames
     *
     * @return string
     */
    protected function _mostSpecific($classes)
    {
        $weights = array_map(
            array("System_Daemon_OS", "_getAncestorCount"),
            $classes
        );
        arsort($weights);
        $fattest = reset(array_keys($weights));
        return $fattest;
    }

    /**
     * Extracts last part of a classname. e.g. System_Daemon_OS_Ubuntu -> Ubuntu
     *
     * @param string $class Full classname
     *
     * @return string
     */
    protected function _getShortHand($class)
    {
        if (!is_string($class) || ! $class ) {
            return false;
        }
        $parts = explode("_", $class);
        return end($parts);
    }

    /**
     * Get the total parent count of a class
     *
     * @param string $class Full classname or instance
     *
     * @return integer
     */
    protected function _getAncestorCount($class)
    {
        return count(System_Daemon_OS::_getAncestors($class));
    }

    /**
     * Get an array of parent classes
     *
     * @param string $class Full classname or instance
     *
     * @return array
     */
    protected function _getAncestors($class)
    {
        $classes = array();
        while ($class = get_parent_class($class)) {
            $classes[] = $class;
        }
        return $classes;
    }
}
