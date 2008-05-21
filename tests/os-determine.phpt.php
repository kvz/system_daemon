<?php

require_once 'tests-config.php';

require_once 'System/Daemon.php';
require_once 'System/Daemon/OS.php';

$osObj = new System_Daemon_OS(); 
$res = $osObj->determine();

echo count($res);

?>