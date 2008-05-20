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
 * Mechanism for validating, getting and setting a predefined set of options.
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
class System_Daemon_Options
{
    /**
     * Keep track of active state for all Options
     *
     * @var array
     */
    private $_options = array();
    
    /**
     * Definitions for all Options
     *
     * @var array
     */
    private $_definitions = array();
    
    /**
     * Wether all the options have been initialized
     *
     * @var boolean
     */
    private $_isInitialized = false;

    /**
     * Holds errors
     *
     * @var array
     */
    public $errors = array();
    
    
    /**
     * Constructor
     * 
     * @param array $definitions The predefined option definitions
     */
    public function __construct($definitions) 
    {
        if (!is_array($definitions) || !count($definitions)) {
            return false;
        }
        
        $this->_definitions = $definitions;
    }
    
    /**
     * Retrieves any option found in $_definitions
     * 
     * @param string $name Name of the Option
     *
     * @return boolean
     */
    public function optionGet($name)
    {
        if (!isset($this->_options[$name])) {
            return null;
        }
        return $this->_options[$name];
    }//end optionGet()    
    
    
    /**
     * Gets an array of options found in $_definitions
     *
     * @return array
     */
    public function optionsGet()
    {
        return $this->_options;
    }//end optionsGet()    
    
    /**
     * Sets any option found in $_definitions
     * 
     * @param string $name  Name of the Option
     * @param mixed  $value Value of the Option
     *
     * @return boolean
     */
    public function optionSet($name, $value)
    {
        // Not validated?
        if (!$this->_optionValidate($name, $value, $reason)) {
            // Default not used or failed as well!
            $this->errors[] = "Option ".$name." invalid: ".$reason;
            return false;
        }
        
        $this->_options[$name] = $value;
    }//end optionSet()
        
    /**
     * Sets an array of options found in $_definitions
     * 
     * @param array $use_options Array with Options
     *
     * @return boolean
     */
    public function optionsSet($use_options)
    {
        $success = true;
        foreach ($use_options as $name=>$value) {
            if (!$this->optionSet($name, $value)) {
                $success = false;
            }
        }
        return $success;
    }//end optionsSet()
    
    /**
     * Wether options are initialized
     *
     * @return boolean
     */
    public function isInitialized()
    {
        return $this->_isInitialized;
    }//end isInitialized()        
    
    /**
     * Checks if all the required options are set & met.
     * Initializes, sanitizes & defaults unset variables
     * 
     * @param boolean $premature Whether to do a premature option init
     *
     * @return mixed integer or boolean
     */
    public function optionsInit($premature=false) 
    {
        // If already initialized, skip
        if (!$premature && $this->isInitialized()) {
            return true;
        }        
        
        $options_met = 0;
        
        foreach ($this->_definitions as $name=>$definition) {
            // Skip non-required options
            if (!isset($definition["required"]) 
                || $definition["required"] !== true ) {
                continue;
            }
            
            // Required options remain
            if (!isset($this->_options[$name])) {                
                if (!$this->_optionSetDefault($name) && !$premature) {
                    $this->errors[] = "Required option: ".$name. 
                        " not set. No default value available either.";
                    return false;
                } 
            }
            
            $options_met++;
        }
                
        if (!$premature) {
            $this->_isInitialized = true;
        }
        
        return $options_met;
        
    }//end optionsInit()       
    
    
    
    /**
     * Validates any option found in $_definitions
     * 
     * @param string $name    Name of the Option
     * @param mixed  $value   Value of the Option
     * @param string &$reason Why something does not validate
     *
     * @return boolean
     */
    private function _optionValidate($name, $value, &$reason="")
    {
        $reason = false;
        
        if (!$reason && !isset($this->_definitions[$name])) {
            $reason = "Option ".$name." not found in definitions";
        }
        
        $definition = $this->_definitions[$name];
        
        if (!$reason && !isset($definition["type"])) {
            $reason = "Option ".$name.":type not found in definitions";
        }
        
        // Compile array of allowd main & subtypes
        $allowedTypes = $this->allowedTypes($definition["type"]);
        
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
                            if ($this->strIsUnix($value)) {
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
                        $this->errors[] =  "Type ".
                            $type_a." not defined";
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
            $this->errors[] = $reason;
            return false;
        }
        
        return true;
    }//end _optionValidate()    

    
    /**
     * Sets any option found in $_definitions to its default value
     * 
     * @param string $name Name of the Option
     *
     * @return boolean
     */
    private function _optionSetDefault($name)
    {
        if (!isset($this->_definitions[$name])) {
            return false;
        }        
        $definition = $this->_definitions[$name];

        if (!isset($definition["type"])) {
            return false;
        }
        if (!isset($definition["default"])) {
            return false;
        }
        
        // Compile array of allowd main & subtypes
        $allowedTypes = $this->allowedTypes($definition["type"]);        
        
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
                        
        $this->_options[$name] = $value;
        return true;
    }//end _optionSetDefault()    
    
    /**
     * Callback function to replace variables in defaults
     *
     * @param array $matches Matched functions
     * 
     * @return string
     */
    private function _optionReplaceVariables($matches)
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
            $source_use = &$this->_options; 
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
    private function _optionReplaceFunctions($matches)
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
     * Compile array of allowed types
     * 
     * @param string $str String that contains allowed type information
     * 
     * @return array      
     */
    protected function allowedTypes($str) 
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