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
    $daemon->log(1, "not writing an init.d script this time");
} else {
    if (($initd_location = $daemon->osInitDWrite()) === false) {
        $daemon->log(2, "unable to write init.d script");
    } else {
        $daemon->log(1, "sucessfully written startup script: ".$initd_location );
    }
}

// Run your code
$runningOkay = true;
$cnt         = 1;
while (!$daemon->daemonIsDying() && $runningOkay && $cnt <=3) {
    // do deamon stuff
    $mode = "'".($daemon->daemonInBackground() ? "" : "non-" )."daemon' mode";
    
    $daemon->log(1, $daemon->appName." running in ".$mode." ".$cnt."/3");
    $runningOkay = true;
    
    // relax the system by sleeping for a little bit
    sleep(2);
    $cnt++;
}


$daemon->stop();
?>