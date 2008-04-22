<?php
/**
 * Script to generate package.xml file
 *
 * Taken from Limb PHP Framework http://limb-project.com 
 * More info http://www.developertutorials.com/pear-manual/developers.packagedef.html
 *
 * @version   SVN: Release: $Id$
 */

require_once 'PEAR/PackageFileManager2.php';
//require_once 'PEAR/Svn.php';

list($name, $baseVersion, $state) = explode('-', trim(file_get_contents(dirname(__FILE__) . '/docs/VERSION')));
$changelog = htmlspecialchars(file_get_contents(dirname(__FILE__) . '/docs/CHANGELOG'));
$summary = htmlspecialchars(file_get_contents(dirname(__FILE__) . '/docs/SUMMARY'));
$description = htmlspecialchars(file_get_contents(dirname(__FILE__) . '/docs/DESCRIPTION'));
$maintainers = explode("\n", trim(file_get_contents(dirname(__FILE__) . '/docs/MAINTAINERS')));

$version = $baseVersion . (isset($argv[3]) ? $argv[3] : '');
$dir = dirname(__FILE__);

$apiVersion = $baseVersion;
$apiStability = $state;

$package = new PEAR_PackageFileManager2();

$result = $package->setOptions(array(
    'package'           => $name,
    'summary'           => $summary,
    'version'           => $version,
    'state'             => $state,
    'description'       => $description,
    'filelistgenerator' => 'file',
    'ignore'            => array('package.php',
                                 'package.xml',
                                 '*.tgz',
                                 'docs'),
    //'simpleoutput'      => true,
    'baseinstalldir'  => 'System',
    'packagedirectory'  => './',
    'packagefile' => 'package.xml',
    'dir_roles' => array('docs' => 'doc',
                         'examples' => 'doc',
                         'tests' => 'test'),
    'roles' => array('*' => 'php'),
));
    
if (PEAR::isError($result)) {
    echo $result->getMessage();
    exit(1);
}

$package->setAPIVersion($apiVersion);
$package->setReleaseVersion($version);
$package->setReleaseStability($state);
$package->setAPIStability($apiStability);
$package->setNotes($changelog);
$package->setPackageType('php');
$package->setLicense('BSD', 'http://www.opensource.org/licenses/bsd-license.php');

foreach ($maintainers as $line) {
    $parts = explode(',', $line);
    list($role, $nick, $name, $email, $active) = $parts;
    //print_r($parts);
    $package->addMaintainer($role, $nick, $name, $email);
}
$package->generateContents();

/*$package->addDependency('php',              '5.2.1', 'ge',  'php', false);
$package->addDependency('PEAR',             '1.3.3', 'ge',  'pkg', false);
$package->addDependency('Linux',            false,   'has', 'os',  false);
*/

$result = $package->writePackageFile();

if (PEAR::isError($result)) {
    echo $result->getMessage();
    exit(1);
}

?>