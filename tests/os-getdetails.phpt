--TEST--
--SKIPIF--
<?php 
if (substr(PHP_OS, 0, 3) == 'WIN') die("skip this test is for non-Windows platforms only");
?>
--FILE--
<?php

require_once 'tests-config.php';

require_once 'System/Daemon.php';
require_once 'System/Daemon/OS.php';

$os = System_Daemon_OS::factory();
$details = $os->getDetails();

echo count($details);

?>
--EXPECT--
2