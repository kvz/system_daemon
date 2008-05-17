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
 * @author    Kevin van Zonneveld <kevin@vanzonneveld.net>
 * @copyright 2008 Kevin van Zonneveld (http://kevin.vanzonneveld.net)
 * @license   http://www.opensource.org/licenses/bsd-license.php New BSD Licence
 * @version   SVN: Release: $Id$
 * @link      http://trac.plutonia.nl/projects/system_daemon
 */

$workspace_dir = realpath(dirname(__FILE__)."");

// Determine build version + corresponding SVN revision basedd on MAPPING file
$mapping = parse_ini_file($workspace_dir."/docs/MAPPING");
asort($mapping);
$build_version  = end(array_keys($mapping));
$build_revision = preg_replace('/[^\d]/', '', end($mapping));

// Read changes up from current revision
$cmd = "php ".$workspace_dir."/tools/changelog_gen.php ".
    $build_revision;
exec($cmd, $o, $r);
if ($r) {
    print_r($o);
    die("command: ".$cmd." failed!\n");
}

// If there are any updates, overwrite changelog
$change_log = trim(implode("\n", $o));
if ($change_log) {
    file_put_contents($workspace_dir."/docs/NOTES", $change_log);
}

// Build XML
if (is_file($workspace_dir."/package.xml")) {
    rename($workspace_dir."/package.xml", $workspace_dir."/package.xml.bak");
}
$cmd = "php ".$workspace_dir."/tools/package_gen.php make";
exec($cmd, $o, $r);
if ($r) {
    print_r($o);
    die("command: ".$cmd." failed!\n");
}

$olddir = getcwd(); 
chdir($workspace_dir);

// Build tgz
$parts    = explode("-", trim(file_get_contents($workspace_dir."/docs/VERSION")));
$packfile = $parts[0]."-".$parts[1].".tgz";
if (is_file($workspace_dir."/".$packfile)) {
    unlink($workspace_dir."/".$packfile);
}
$cmd = "pear package";
exec($cmd, $o, $r);
if ($r) {
    print_r($o);
    die("command: ".$cmd." failed!\n");
}
chdir($olddir);
?>