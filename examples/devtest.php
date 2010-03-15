#!/usr/bin/php -q
<?php
/**
 * System_Daemon turns PHP-CLI scripts into daemons.
 * 
 * PHP version 5
 *
 * @category  System
 * @package   System_Daemon
 * @author    Kevin <kevin@vanzonneveld.net>
 * @copyright 2008 Kevin van Zonneveld
 * @license   http://www.opensource.org/licenses/bsd-license.php New BSD Licence
 * @link      http://github.com/kvz/system_daemon
 */

/**
 * System_Daemon Example Code
 * 
 * If you run this code successfully, a daemon will be spawned
 * and stopped directly. You should find a log enty in 
 * /var/log/optest.log
 * 
 */

// Make it possible to test in source directory
// This is for PEAR developers only
ini_set('include_path', ini_get('include_path').':..');

// Include Class
error_reporting(E_ALL);
require_once 'System/Daemon.php';

// Bare minimum setup
System_Daemon::setOption('appName', 'optest');
System_Daemon::setOption('authorEmail', 'kevin@vanzonneveld.net');
System_Daemon::setOption('logLocation', '/var/log/sysdaemon.devtest.log');
System_Daemon::setOption('logFilePosition', true);

System_Daemon::warning('{appName} daemon encountered an empty appPidLocation');
System_Daemon::err('{appName} daemon encountered an empty appPidLocation');

die();

$options                   = array();
$options['appName']        = 'devtest';
$options['appExecutable']  = 'devtest.php';
$options['appDir']         = realpath(dirname(__FILE__));
$options['appDescription'] = 'Developer test daemon';
$options['authorName']     = 'kevman';
$options['authorEmail']    = 'kev@man.com';

if (($os = System_Daemon_OS::factory('BSD')) === false) {
    echo 'Cannot create OS\n';
} else {
    print_r($os->errors);
    
    echo '\n';
    
    echo $os->getAutoRunTemplatePath();
    echo '\n';
    
    $details = $os->getDetails();
    echo '\n';
    print_r($details);
    echo '\n';
}




die ();
if (($res = $os->writeAutoRun($options, true)) === false) {
    print_r($os->errors);
} elseif ($res === true) {
    echo 'alread written\n';
} else {
    echo 'written to '.$res.'\n';
}




/*if (!$os->setAutoRunProperties($options)) {
    print_r($os->errors);
}
*/




die();


// Bare minimum setup
System_Daemon::setOption('appName', 'optest');
System_Daemon::setOption('authorEmail', 'kevin@vanzonneveld.net');

die();

//System_Daemon::setOption('appDir', dirname(__FILE__));
System_Daemon::log(System_Daemon::LOG_INFO, 'Daemon not yet started so '.
    'this will be written on-screen');

// Spawn Deamon!
System_Daemon::start();
System_Daemon::log(System_Daemon::LOG_INFO, 'Daemon: \''.
    System_Daemon::getOption('appName').
    '\' spawned! This will be written to '.
    System_Daemon::getOption('logLocation'));

// Your normal PHP code goes here. Only the code will run in the background
// so you can close your terminal session, and the application will
// still run.

System_Daemon::stop();