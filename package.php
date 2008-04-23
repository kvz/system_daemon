<?php
/**
 * Script to generate package.xml file
 *
 * Taken from Limb PHP Framework http://limb-project.com 
 * More info 
 *  http://www.developertutorials.com/pear-manual/developers.packagedef.html
 *  http://blog.astrumfutura.com/plugin/blogpdf
 *
 * @version   SVN: Release: $Id$
 */

list($name, $baseVersion, $state) = explode('-', trim(file_get_contents(dirname(__FILE__) . '/docs/VERSION')));
$notes = htmlspecialchars(file_get_contents(dirname(__FILE__) . '/docs/NOTES'));
$summary = htmlspecialchars(file_get_contents(dirname(__FILE__) . '/docs/SUMMARY'));
$description = htmlspecialchars(file_get_contents(dirname(__FILE__) . '/docs/DESCRIPTION'));
$maintainers = file(dirname(__FILE__) . '/docs/MAINTAINERS');

$version = $baseVersion . (isset($argv[3]) ? $argv[3] : '');
$dir = dirname(__FILE__);

$apiVersion = $baseVersion;
$apiStability = $state;

require_once 'PEAR/PackageFileManager2.php';
PEAR::setErrorHandling(PEAR_ERROR_DIE);

$options = array(
    'package'           => $name,
    'summary'           => $summary,
    'version'           => $version,
    'state'             => $state,
    'description'       => $description,
    'notes'             => $notes,
    'filelistgenerator' => 'svn',
    'ignore'            => array('package2.php',
                                 'package.xml',
                                 '*.tgz',
                                 '.svn',
                                 'docs'
                            ),
    'simpleoutput'      => true,
    'clearcontents'     => true,
    'baseinstalldir'    => 'System',
    'packagedirectory'  => dirname(__FILE__),
    'packagefile'       => 'package.xml',
    'dir_roles'         => array(
                                'docs' => 'doc',
                                'examples' => 'doc',
                                'tests' => 'test'
                            ),
    'roles'             => array(
                                '*' => 'php'
                            )
);


$packagexml = new PEAR_PackageFileManager2;
$e = $packagexml->setOptions($options);

// Oddly enough, this is a PHP source code package...
$packagexml->setPackageType('php');
// Package name, summary and longer description
$packagexml->setPackage($name);
$packagexml->setSummary($summary);
$packagexml->setDescription($description);
// The channel where this package is hosted. Since we're installing from a local
// downloaded file rather than a channel we'll pretend it's from PEAR.
$packagexml->setChannel('pear.php.net');

foreach ($maintainers as $line) {
    list($role, $nick, $name, $email, $active) = explode(',', $line);
    $packagexml->addMaintainer($role, $nick, $name, $email, $active);
}

$packagexml->setNotes($notes);
// Add any known dependencies such as PHP version, extensions, PEAR installer
$packagexml->setPhpDep('5.1.2'); // spl_autoload_register
$packagexml->setPearinstallerDep('1.4.0');
$packagexml->addPackageDepWithChannel('optional', 'PEAR', 'pear.php.net', '1.4.0');
$packagexml->setOSInstallCondition('(*ix|*ux|darwin*|*BSD|SunOS*)');
//$packagexml->addPackageDepWithChannel('php',              '5.2.1', 'ge',  'php', false);
//$packagexml->addPackageDepWithChannel('PEAR',             '1.3.3', 'ge',  'pkg', false);
//$packagexml->addPackageDepWithChannel('Linux',            false,   'has', 'os',  false);


// Other info, like the Lead Developers. license, version details and stability type
$packagexml->setLicense('New BSD License', 'http://opensource.org/licenses/bsd-license.php');
$packagexml->setAPIVersion($baseVersion);
$packagexml->setAPIStability($state);
$packagexml->setReleaseVersion($baseVersion);
$packagexml->setReleaseStability($state);
// Add this as a release, and generate XML content
$packagexml->addRelease();

$packagexml->generateContents();

if (isset($_GET['make']) || (isset($_SERVER['argv']) && @$_SERVER['argv'][1] == 'make')) {
    $packagexml->writePackageFile();
} else {
    $packagexml->debugPackageFile();
}


/*
$result = $package->writePackageFile();

if (PEAR::isError($result)) {
    echo $result->getMessage();
    exit(1);
}
*/
?>