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
 * A System_Daemon_OS driver for BSD
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
class System_Daemon_OS_BSD extends System_Daemon_OS
{
    /**
     * Template path
     *
     * @var string
     */
    protected $autoRunTemplatePath = false;
    
    /**
     * Determines wether the system is compatible with this OS
     *
     * @return boolean
     */
    public function isInstalled() 
    {
        if (!stristr(PHP_OS, "Darwin") && 
            !stristr(PHP_OS, "BSD")) {
            return false;
        }
        
        return true;
    }//end isInstalled
    
    /**
     * Returns a template path to base the autuRun script on.
     * Uses $autoRunTemplatePath if possible. 
     *
     * @return unknown
     * @see autoRunTemplatePath
     */
    public function getAutoRunTemplatePath() 
    {
        $dir        = false;
        $file       = "template_BSD";
        $tried_dirs = array();
        
        if (class_exists("PEAR_Config", true)) {
            $try_dir = realpath(PEAR_Config::singleton()->get("data_dir") ."/System_Daemon");
            if (!is_dir($try_dir)) {
                $tried_dirs[] = $try_dir;
            } else {
                $dir = $try_dir;
            }
        }
        
        if (!$dir) {
            $try_dir = realpath(dirname(__FILE__)."../../../../data");
            if (!is_dir($try_dir)) {
                $tried_dirs[] = $try_dir;
            } else {
                $dir = $try_dir;
            }
        }
        
        if (!$dir) {
            $this->errors[] = "No data dir found in either: ".implode(" or ", $tried_dirs);
            return false;
        }
                
        return $dir."/".$file;
    }//end getAutoRunTemplatePath        
    
}//end class
?>