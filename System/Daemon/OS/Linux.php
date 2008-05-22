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
     * Path to init.d script
     *
     * @var string
     */
    protected $autoRunDir = "/etc/init.d";    
    
    /**
     * Template path
     *
     * @var string
     */
    protected $autoRunTemplatePath = "";    
        
    /**
     * Replace the following keys with values to convert a template into
     * a read autorun script
     *
     * @var array
     */
    protected $autoRunTemplateReplace = array();
    
    
    
    /**
     * Determines wether this the system is compatible with this OS
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

    
    public function getAutoRunPath($appName) 
    {
        if (!$this->autoRunPath) {
            return false;
        }
        
        return $this->autoRunPath."/".$appName;
    }//end getAutoRunPath
    
    public function getAutoRunTemplate() 
    {
        if (!$this->autoRunTemplatePath) {
            return false;
        }
        
        if (!file_exists($this->autoRunTemplatePath)) {
            return false;
        }
        
        return file_get_contents($this->autoRunTemplatePath);

    }//end getAutoRunTemplate    
    
    public function getAutoRunScript()
    {
        $template = $this->getAutoRunTemplate();
        
        if (!$template) {
            return false;
        }
        
        if (!$this->autoRunTemplateReplace 
            || !is_array($this->autoRunTemplateReplace)
            || !count($this->autoRunTemplateReplace)) {
            return false;
        }

        // REPLACE {} vars with real values!!!
        
        return str_replace(array_keys($this->autoRunTemplateReplace), 
            array_values($this->autoRunTemplateReplace), 
            $template);        
        
    }//end getAutoRunScript()    
    
}//end class
?>