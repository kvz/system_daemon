<?php

require_once 'tests-config.php';

require_once 'System/Daemon.php';
require_once 'System/Daemon/OS.php';

$os = System_Daemon_OS::factory();
$details = $os->getDetails();

echo count($details);