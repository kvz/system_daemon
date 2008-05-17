<?php
/* vim: set noai expandtab tabstop=4 softtabstop=4 shiftwidth=4: */
/**
 * Script to set test parameters.
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
    
    $path = ini_get('include_path');
    ini_set('include_path', realpath('../') . PATH_SEPARATOR . $path);

?>