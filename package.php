#!/usr/bin/php -q
<?php
/* vim: set noai expandtab tabstop=4 softtabstop=4 shiftwidth=4: */
/**
 * Script to generate changelog file, store it in package docs
 * and generates PEAR package. Uses package_gen.php & changelog_gen.php
 *  
 * PHP version 5
 * 
 * @category  System
 * @package   System_Daemon
 * @author    Kevin <kevin@vanzonneveld.net>
 * @copyright 2008 Kevin van Zonneveld (http://kevin.vanzonneveld.net)
 * @license   http://www.opensource.org/licenses/bsd-license.php New BSD Licence
 * @version   SVN: Release: $Id$
 * @link      http://trac.plutonia.nl/projects/system_daemon
 */


$workspace_dir = realpath(dirname(__FILE__)."");

$parts    = explode("-", trim(file_get_contents($workspace_dir."/docs/VERSION")));
$packfile = $parts[0]."-".$parts[1].".tgz";

if (is_file($workspace_dir."/".$packfile)) {
    unlink($workspace_dir."/".$packfile);
}

if (is_file($workspace_dir."/package.xml")) {
    rename($workspace_dir."/package.xml", $workspace_dir."/package.xml.bak");
}


$cmd = "php ".$workspace_dir."/tools/changelog_gen.php > ".
    $workspace_dir."/docs/NOTES";
exec($cmd, $o, $r);
if ($r) {
    print_r($o);
    die("command: ".$cmd." failed!\n");
}

$cmd = "php ".$workspace_dir."/tools/package_gen.php make";
exec($cmd, $o, $r);
if ($r) {
    print_r($o);
    die("command: ".$cmd." failed!\n");
}

$olddir = getcwd(); 
chdir($workspace_dir);

$cmd = "pear package";
exec($cmd, $o, $r);
if ($r) {
    print_r($o);
    die("command: ".$cmd." failed!\n");
}
chdir($olddir);
?>