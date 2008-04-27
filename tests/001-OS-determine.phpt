--TEST--
--FILE--
<?php

require_once 'System/Daemon/OS.php';

$res = System_Daemon_OS::determine();

print_r($res);

?>
--EXPECTREGEX--
^Array
(
    [main] => ([a-zA-Z0-9\-\_])+
    [distro] => ([a-zA-Z0-9\-\_])+
    [version] => (.*)
)$