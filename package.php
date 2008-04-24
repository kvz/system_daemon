#!/usr/bin/php -q
<?php
    $workspace_dir = realpath(dirname(__FILE__)."");

    $parts    = explode("-", trim(file_get_contents($workspace_dir."/docs/VERSION")));
    $packfile = $parts[0]."-".$parts[1].".tgz";
    
    if (is_file($workspace_dir."/".$packfile)) {
        unlink($workspace_dir."/".$packfile);
    }
    
    if (is_file($workspace_dir."/package.xml")) {
        rename($workspace_dir."/package.xml", $workspace_dir."/package.xml.bak");
    }
    
    
    $cmd = "php ".$workspace_dir."/tools/changelog_gen.php > ".$workspace_dir."/docs/NOTES";
    exec($cmd, $o, $r);
    if ($r) {
        print_r($o);
        die("command: ".$cmd." failed!\n");
    }

    $cmd = "php ".$workspace_dir."/tools/package_gen.php make";
    exec($cmd, $o, $r);
    if ($r) {
        print_r($o);
        die("command: ".$cmd." failed!\n");
    }
    
    $olddir = chdir($workspace_dir);
    $cmd = "pear package";
    exec($cmd, $o, $r);
    if ($r) {
        print_r($o);
        die("command: ".$cmd." failed!\n");
    }
    chdir($olddir);
?>