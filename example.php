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
 * @version   SVN: Release: $Id$
 * @link      http://trac.plutonia.nl/projects/system_daemon
 */

/**
 * System_Daemon Example Code
 * 
 * If you run this code successfully, a daemon will be spawned
 * but unless have already generated the init.d script, you have
 * no real way of killing it yet.
 * 
 * In this case wait 3 runs, which is the maximum in this example. 
 * 
 * If you're inpatient you can also type:
 * killall -9 example.php
 * OR:
 * killall -9 php
 * 
 */

error_reporting(E_ALL);

// Arguments 
$runmode                = array();
$runmode["no-daemon"]   = false;
$runmode["help"]        = false;
$runmode["write-initd"] = false;
foreach ($argv as $k=>$arg) {
    if (substr($arg, 0, 2) == "--" && isset($runmode[substr($arg, 2)])) {
        $runmode[substr($arg, 2)] = true;
    }
}

// Help
if ($runmode["help"] == true) {
    echo "Usage: ".$argv[0]." [runmode]\n";
    echo "Available runmodes:\n"; 
    foreach ($runmode as $runmod=>$val) {
        echo " --".$runmod."\n";
    }
    die();
}
    
// Spawn Daemon 
// conditional so use include
if (!include "System/Daemon.php") {
    die("Unable to locate System_Daemon class\n");
}

$daemon                 = new System_Daemon("logparser", true);
$daemon->appDir         = dirname(__FILE__);
$daemon->appDescription = "Parses logfiles of vsftpd and stores them in MySQL";
$daemon->authorName     = "Kevin van Zonneveld";
$daemon->authorEmail    = "kevin@vanzonneveld.net";
if (!$runmode["no-daemon"]) {
    $daemon->start();
}
    
if (!$runmode["write-initd"]) {
    echo "Not writing an init.d script this time\n";
} else {
    echo "Writing an init.d script: ";
    if (!$daemon->osInitDWrite()) {
        echo "failed!\n";
    } else {
        echo "OK\n";
    }
}

// Run your code
$runningOkay = true;
$runCount = 1;
while (!$daemon->daemonIsDying() && $runningOkay && $runCount <=3) {
    // do deamon stuff
    echo $daemon->appName." process is running in ";
    if ($daemon->daemonInBackground()) {
        echo "'daemon' ";
    } else{
        echo "'non-daemon' ";
    }
    echo "mode (run ".$runCount." of 3)\n";
    $runningOkay = true;
    
    // relax the system by sleeping for a little bit
    sleep(2);
    $runCount++;
}

echo "Stopping ".$daemon->appName."\n";
$daemon->stop();
?>