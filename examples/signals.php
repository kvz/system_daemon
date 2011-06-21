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
 * @license   http://www.opensource.org/licenses/bsd-license.php
 * @link      http://github.com/kvz/system_daemon
 */

/**
 * System_Daemon Example Code
 * 
 * If you run this code successfully, a daemon will be spawned
 * but unless have already generated the init.d script, you have
 * no real way of killing it yet.
 * 
 * In this case wait 3 runs, which is the maximum for this example. 
 * 
 * 
 * In panic situations, you can always kill you daemon by typing
 * 
 * killall -9 signals.php
 * OR:
 * killall -9 php
 */

// Make it possible to test in source directory
// This is for PEAR developers only
ini_set('include_path', ini_get('include_path').':..');

// Include Class
error_reporting(E_ALL);
require_once 'System/Daemon.php';

// Setup
System_Daemon::setOptions(array(
    'appName' => 'signals',
    'appDir' => dirname(__FILE__),
    'appDescription' => 'Showcases how you could catch POSIX signals',
    'authorName' => 'Kevin van Zonneveld',
    'authorEmail' => 'kevin@vanzonneveld.net',
    'sysMaxExecutionTime' => '0',
    'sysMaxInputTime' => '0',
    'sysMemoryLimit' => '1024M',
    'appRunAsGID' => 1000,
    'appRunAsUID' => 1000,
));

// Overrule the signal handler with any function
System_Daemon::setSigHandler(SIGTERM, 'myHandler');

function myHandler($signal) {
    if ($signal === SIGTERM) {
        System_Daemon::warning('I received the termination signal. ' . $signal);
        // Execute some final code
        // and be sure to:
        System_Daemon::stop();
    }
}


// Spawn Daemon 
System_Daemon::start();

// Here comes your own actual code

// This variable keeps track of how many 'runs' or 'loops' your daemon has
// done so far. For example purposes, we're quitting on 3.
$cnt = 1;

// While checks on 2 things in this case:
// - That the Daemon Class hasn't reported it's dying
// - That we're not executing more than 3 runs 
while (!System_Daemon::isDying() && $cnt <=3) {
    // Log something using the Daemon class's logging facility
    // Depending on runmode it will either end up:
    //  - In the /var/log/logparser.log
    //  - On screen (in case we're not a daemon yet)  
    System_Daemon::info('{appName} running %s/3',
        $cnt
    );
    
    // Relax the system by sleeping for a little bit
    // iterate() also clears statcache
    System_Daemon::iterate(2);

    // Just here to showcase how sighandlers can work
    // to catch a
    //   /etc/init.d/signals stop
    // The SIGTERM signal tells the daemon to quit.
    // Normally it's catched by the ::defaultSigHandler()
    // but now we catch it with myHandler()
    posix_kill(posix_getpid(), SIGTERM);

    $cnt++;
}

// Shut down the daemon nicely
// This is ignored if the class is actually running in the foreground
System_Daemon::stop();