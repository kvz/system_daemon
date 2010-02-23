#!/usr/bin/php -q
<?php
/* vim: set noai expandtab tabstop=4 softtabstop=4 shiftwidth=4: */
/**
 * Script to test package. Uses phpcs and phpt
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
 * Executes a command
 * 
 * @param string $cmd Command to execute
 * @param array  &$o  Where output is stored 
 * 
 * @return boolean
 */
function exe($cmd, &$o) 
{
    $x = @exec($cmd, $o, $r);
    if ($r) {
        return false;
    }    
    return true;
}

$workspace_dir     = realpath(dirname(__FILE__)."");
$cmd_reqs          = array();
$cmd_reqs["phpcs"] = "PHP_CodeSniffer (pear install -f PHP_CodeSniffer)";
$cmd_reqs["phpt"]  = "PHPT, http://phpt.info/wiki";

// check if commands are available
foreach ($cmd_reqs as $cmd=>$package) {
    if (@exe("which ".$cmd, $lines) === false) {
        echo $cmd." is not available. ";
        echo "Please first install the ".$package;            
        die("\n");
    }
}

$cmd = "phpcs --standard=PEAR ".$workspace_dir."/System";
// 2>&1 |grep -v 'underscore' -B2
@exe($cmd, $lines);
echo implode("\n", $lines); 

$cmd = "phpt -r ".$workspace_dir."";
@exe($cmd, $lines);
echo implode("\n", $lines); 