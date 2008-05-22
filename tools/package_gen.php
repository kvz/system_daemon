#!/usr/bin/php -q
<?php
/* vim: set noai expandtab tabstop=4 softtabstop=4 shiftwidth=4: */
/**
 * Script to generate package.xml file
 *
 * Parts taken from Limb PHP Framework http://limb-project.com 
 * More info 
 *  http://www.developertutorials.com/pear-manual/developers.packagedef.html
 *  http://blog.astrumfutura.com/plugin/blogpdf
 *  http://trac.piece-framework.com/piece-unity/browser/trunk/package.php?rev=887
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

$workspace_dir = realpath(dirname(__FILE__)."/..");

list($name, $baseVersion, $state) = explode("-", 
    trim(file_get_contents($workspace_dir . "/docs/VERSION")));
    
$notes       = htmlspecialchars(file_get_contents($workspace_dir . 
    "/docs/NOTES"));
$summary     = htmlspecialchars(file_get_contents($workspace_dir . 
    "/docs/SUMMARY"));
$description = htmlspecialchars(file_get_contents($workspace_dir . 
    "/docs/DESCRIPTION"));
$maintainers = file($workspace_dir . "/docs/MAINTAINERS");

$version = $baseVersion . (isset($argv[3]) ? $argv[3] : "");
$dir     = $workspace_dir;

$apiVersion   = $baseVersion;
$apiStability = $state;

require_once "PEAR/PackageFileManager2.php";
PEAR::setErrorHandling(PEAR_ERROR_DIE);

$options = array(
    "package"           => $name,
    "summary"           => $summary,
    "version"           => $version,
    "state"             => $state,
    "description"       => $description,
    "notes"             => $notes,
    "filelistgenerator" => "svn",
    "ignore"            => array(   "package2.php",
                                    "package.xml",
                                    "*.tgz",
                                    ".svn",
                                    ".project",
                                    "docs",
                                    "tools"
                            ),
    "simpleoutput"      => true,
    "clearcontents"     => true,
    "baseinstalldir"    => "./",
    "packagedirectory"  => $workspace_dir,
    "packagefile"       => "package.xml",
    "dir_roles"         => array(
                                "docs" => "doc",
                                "examples" => "doc",
                                "tests" => "test",
                                "data" => "data"
                            ),
    "roles"             => array(
                                "*" => "php"
                            )
);


$packagexml = new PEAR_PackageFileManager2;
$e          = $packagexml->setOptions($options);

// Oddly enough, this is a PHP source code package...
$packagexml->setPackageType("php");
// Package name, summary and longer description
$packagexml->setPackage($name);
$packagexml->setSummary($summary);
$packagexml->setDescription($description);
// The channel where this package is hosted. Since we"re installing from a local
// downloaded file rather than a channel we"ll pretend it"s from PEAR.
$packagexml->setChannel("pear.php.net");

foreach ($maintainers as $line) {
    list($role, $nick, $name, $email, $active) = explode(",", $line);
    $packagexml->addMaintainer($role, $nick, $name, $email, $active);
}

$packagexml->setNotes($notes);
// Add any known dependencies such as PHP version, extensions, PEAR installer
$packagexml->setPhpDep("5.1.2"); // spl_autoload_register
$packagexml->setPearinstallerDep("1.4.0");
$packagexml->setOSInstallCondition("(*ix|*ux|darwin*|*BSD|SunOS*)");
$packagexml->addPackageDepWithChannel("optional", "Log", "pear.php.net", "1.0"); 

// Other info, like the Lead Developers. license, version details 
// and stability type
$packagexml->setLicense("New BSD License", 
    "http://opensource.org/licenses/bsd-license.php");
$packagexml->setAPIVersion($baseVersion);
$packagexml->setAPIStability($state);
$packagexml->setReleaseVersion($baseVersion);
$packagexml->setReleaseStability($state);
// Add this as a release, and generate XML content
$packagexml->addRelease();

$packagexml->generateContents();

if (isset($_GET["make"]) 
    || (isset($_SERVER["argv"]) 
    && @$_SERVER["argv"][1] == "make")) {
    $packagexml->writePackageFile();
} else {
    $packagexml->debugPackageFile();
}
?>