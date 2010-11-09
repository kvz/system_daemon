<?php
require_once "../System/Daemon.php";
System_Daemon::setOption("appName", "leakplugger");
System_Daemon::start();
$last = 0;
while (!System_Daemon::isDying()){
    $mem = memory_get_peak_usage();
    $use = $mem - $last;
    if ($use >= 0) $use = '+' . $use;

    System_Daemon::info("test");
    echo "debug: memory_get_peak_usage: " . $use . "\n";

    $last = $mem;
    System_Daemon::iterate(1);
}
