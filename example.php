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
 */

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
set_time_limit(0);
ini_set("memory_limit", "1024M");
if ($runmode["no-daemon"] == false) {
    // conditional so use include
    include_once dirname(__FILE__)."/ext/System_Daemon/Daemon.Class.php";
    
    $daemon                  = new Daemon("mydaemon");
    $daemon->app_dir         = dirname(__FILE__);
    $daemon->app_description = "My 1st Daemon";
    $daemon->author_name     = "Kevin van Zonneveld";
    $daemon->author_email    = "kevin@vanzonneveld.net";
    $daemon->start();
    
    if ($runmode["write-initd"]) {
        if (!$daemon->initd_write()) {
            echo "Unable to write init.d script\n";
        } else {
            echo "I wrote an init.d script\n";
        }
    }
}

        
// Run your code
$fatal_error = false;
while (!$fatal_error && !$daemon->is_dying) {
    // do deamon stuff
    echo $daemon->app_dir." daemon is running...\n";
    
    // relax the system by sleeping for a little bit
    sleep(5);
}

$daemon->stop();
?>