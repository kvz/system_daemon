#!/usr/bin/php -q
<?php

// Make it possible to test in source directory
// This is for PEAR developers only
ini_set('include_path', ini_get('include_path').':..');

// Include Class
error_reporting(E_ALL);

// Start System Daemon (PEAR)
require_once "System/Daemon.php";

// Allowed arguments & their defaults
$runmode = array(
	"no-daemon" 	=> true,
	"help" 			=> false,
	"write-initd" 	=> false
);

// Options
$options = array(
	'appName' 				=> 'queue_test',
	'appDir' 				=> dirname(__FILE__),
	'sysMaxExecutionTime' 	=> '0',
	'sysMaxInputTime' 		=> '0',
	'sysMemoryLimit' 		=> '1024M'
);
System_Daemon::setOptions($options);

// Overrule the signal handler with any function
System_Daemon::setSigHandler(SIGCONT, array("System_Daemon", "defaultSigHandler"));

System_Daemon::start();
System_Daemon::restart();
System_Daemon::stop();