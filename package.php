<?php
/**
 * Script to generate package.xml file
 *
 * Taken from Information:LiveUser, thanks arnaud ;)
 * Original from PEAR::Log, thanks Jon ;)
 * More info http://www.developertutorials.com/pear-manual/developers.packagedef.html
 *
 * @version   SVN: Release: $Id$
 */
require_once 'PEAR/PackageFileManager.php';
require_once 'Console/Getopt.php';

$packagefile = 'package.xml';

$version = '';
$apiVersion = '';

$notes = <<<EOT
EOT;

$description = <<<EOT
  System_Daemon turns PHP-CLI scripts into daemons
EOT;

$options = array('filelistgenerator' => 'svn',
    'package'           => 'Daemon',
    'summary'           => 'Turn PHP scripts into Linux daemons',
    'description'       => $description,
    'version'           => $version,
    'state'             => 'beta',
    'license'           => 'BSD',
    'filelistgenerator' => 'svn',
    'ignore'            => array('package.php', 'package.xml', '.project', '.settings'),
    'clearcontents'     => false,
    'changelogoldtonew' => false,
    'simpleoutput'      => true,
    'packagedirectory'  => './',
    'dir_roles'         => array('sql'               => 'data',
                                 'docs'              => 'doc',
                                 'scripts'           => 'script')
);


$package = new PEAR_PackageFileManager;
//$package = $package::importOptions($packagefile, $options);
$package->setPackageType('php');
$package->addRelease();
$package->generateContents();
$package->setReleaseVersion($version);
$package->setAPIVersion($apiVersion);
$package->setReleaseStability('beta');
$package->setAPIStability('beta');
$package->setNotes($notes);

if (PEAR::isError($result)) {
    echo $result->getMessage();
    exit();
}

$package->addMaintainer('kevin',   'lead',      'Kevin van Zonneveld' ,'kevin@vanzonneveld.net');

$package->addDependency('php',              '5.2.1', 'ge',  'php', false);
$package->addDependency('PEAR',             '1.3.3', 'ge',  'pkg', false);
$package->addDependency('Linux',            false,   'has', 'os',  false);

if (array_key_exists('make', $_GET) || (isset($_SERVER['argv'][1]) && $_SERVER['argv'][1] == 'make')) {
    echo "package.xml generated\n";
    $result = $package->writePackageFile();
} else {
    $result = $package->debugPackageFile();
}

if (PEAR::isError($result)) {
    echo $result->getMessage();
    exit();
}



/**
 *     'changelogoldtonew' => false,
    'notes'             => $notes,
    'baseinstalldir'    => '/LiveUser',
    'installexceptions' => array(
        'LiveUser.php'            => '/',
    ),
    'installas'         => array(
        'sql/Auth_XML.xml'           => 'misc/Auth_XML.xml',
        'sql/Perm_XML.xml'           => 'misc/Perm_XML.xml',
        'sql/README'                 => 'misc/schema/README',
        'sql/install.php'            => 'misc/schema/install.php',
    ),
    'exceptions'         => array(
        'lgpl.txt' => 'doc'
    ),
 * 
 */

?>