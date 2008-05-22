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
 * A System_Daemon_OS driver for Linux based Operating Systems
 *
 * @category  System
 * @package   System_Daemon
 * @author    Kevin van Zonneveld <kevin@vanzonneveld.net>
 * @copyright 2008 Kevin van Zonneveld (http://kevin.vanzonneveld.net)
 * @license   http://www.opensource.org/licenses/bsd-license.php New BSD Licence
 * @version   SVN: Release: $Id$
 * @link      http://trac.plutonia.nl/projects/system_daemon
 * * 
 */
class System_Daemon_OS_Linux extends System_Daemon_OS
{
    /**
     * On Linux, a distro-specific version file is often telling us enough
     *
     * @var string
     */
    protected $osVersionFile = "";
    
    /**
     * Path to autoRun script
     *
     * @var string
     */
    protected $autoRunDir = "/etc/init.d";    
    
    
    /**
     * Determines wether the system is compatible with this OS
     *
     * @return boolean
     */
    public function isInstalled() 
    {
        if (!stristr(PHP_OS, "Linux")) {
            return false;
        }
        
        // Find out more specific
        // This is used by extended classes that inherit
        // this function
        if ($this->osVersionFile) {
            if (!file_exists($this->osVersionFile)) {
                return false;
            } 
        } 
        
        return true;
    }//end isInstalled
        
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
        if (($template = $this->getAutoRunTemplate()) === false) {
            return false;
        }
        if (!$this->autoRunTemplateReplace 
            || !is_array($this->autoRunTemplateReplace)
            || !count($this->autoRunTemplateReplace)) {
            return false;
        }
        
        // Replace System specific keywords with Universal placeholder keywords
        $script = str_replace(array_keys($this->autoRunTemplateReplace), 
            array_values($this->autoRunTemplateReplace), 
            $template);
        
        // Replace Universal placeholder keywords with Daemon specific properties
        if (!preg_match_all('/(\{PROPERTIES([^\}]+)\})/is', $script, $r)) {
            $this->errors[] = "No PROPERTIES found in autoRun template";
            return false;
        }
        
        $placeholders = $r[1];
        array_unique($placeholders);
        foreach ($placeholders as $placeholder) {
            // Get var
            $var    = str_replace(array("{PROPERTIES.", "}"), "", $placeholder);
            
            // Replace placeholder with actual daemon property
            $script = str_replace($placeholder, $properties[$var], $script);
        }
        
        return $script;        
    }//end getAutoRunScript()    
    
}//end class
?>