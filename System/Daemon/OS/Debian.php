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
 * A System_Daemon_OS driver for Debian based Operating Systems (including Ubuntu)
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
class System_Daemon_OS_Debian extends System_Daemon_OS_Linux
{
    /**
     * On Linux, a distro-specific version file is often telling us enough
     *
     * @var string
     */
    protected $osVersionFile = "/etc/debian_version";
    
    /**
     * Template path
     *
     * @var string
     */
    protected $autoRunTemplatePath = "/etc/init.d/skeleton";    
    
    /**
     * Replace the following keys with values to convert a template into
     * a read autorun script
     *
     * @var array
     */    
    protected $autoRunTemplateReplace = array(
        "Foo Bar" => "{PROPERTIES.authorName}",
        "foobar@baz.org" => "{PROPERTIES.authorEmail}",
        "daemonexecutablename" => "{PROPERTIES.appName}",
        "Example" => "{PROPERTIES.appName}",
        "skeleton" => "{PROPERTIES.appName}",
        "/usr/sbin/\$NAME" => "{PROPERTIES.appDir}/{PROPERTIES.appExecutable}",
        "Description of the service"=> "{PROPERTIES.appDescription}",
        " --name \$NAME" => "",
        "--options args" => "",
        "# Please remove the \"Author\" lines above and replace them" => "",
        "# with your own name if you copy and modify this script." => ""
    );
    
}//end class
?>