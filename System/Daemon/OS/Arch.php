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
 * A System_Daemon_OS driver for Arch Linux
 *
 * @category  System
 * @package   System_Daemon
 * @author    Tomáš Klapka <tomas@klapka.cz>
 * @copyright 2012 Tomáš Klapka
 * @license   http://www.opensource.org/licenses/bsd-license.php New BSD Licence
 * @version   SVN: Release: $Id$
 * @link      http://trac.plutonia.nl/projects/system_daemon
 * *
 */
class System_Daemon_OS_Arch extends System_Daemon_OS_Ubuntu
{
    /**
     * On Linux, a distro-specific version file is often telling us enough
     *
     * @var string
     */
    protected $_osVersionFile = "";

    /**
     * Path to autoRun script
     *
     * @var string
     */
    protected $_autoRunDir = "/etc/rc.d";

    /**
     * Template path
     *
     * @var string
     */
    protected $_autoRunTemplatePath = '#datadir#/template_Arch';


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

        $file = '/etc/issue';
        if (file_exists($file) and
            is_readable($file)
        ) {
            $f = fopen($file, "r");
            $issue = '';
            if ($f) {
                $issue = fread($f, 10);
            }
            fclose($f);
            if ($issue == 'Arch Linux') {
                return true;
            }
        }
        return false;
    }

}