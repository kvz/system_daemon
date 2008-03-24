#!/usr/bin/php -q
<?php
    /********************************************************************
     * Arguments 
     ********************************************************************/
    $runmode = array();
    $runmode["no-daemon"] = false;
    $runmode["help"] = false;
    foreach($argv as $k=>$arg){
        if(substr($arg, 0, 2) == "--" && isset($runmode[substr($arg, 2)])){
            $runmode[substr($arg, 2)] = true;
        }
    }
    
    // help
    if($runmode["help"] == true){
        echo "Usage: ".$argv[0]." [runmode]\n";
        echo "Available runmodes:\n"; 
        foreach($runmode as $runmod=>$val){
            echo " --".$runmod."\n";
        }
        die();
    }
    
    /********************************************************************
     * Spawn Daemon 
     ********************************************************************/
    set_time_limit(0);
    ini_set("memory_limit","1024M");
    if($runmode["no-daemon"] == false){
        require_once dirname(__FILE__)."/ext/System_Daemon/Daemon.Class.php";
        
        $daemon = new Daemon("mydaemon");
        $daemon->app_dir = dirname(__FILE__);
        $daemon->app_description = "My 1st Daemon";
        $daemon->author_name = "Kevin van Zonneveld";
        $daemon->author_email = "kevin@vanzonneveld.net";
        $daemon->start();
        
        if($daemon->initd_write()){
            echo "I wrote an init.d script\n";
        }
    }

            
    /********************************************************************
     * Run 
     ********************************************************************/
    $fatal_error = false;
    while(!$fatal_error && !$daemon->is_dying){
        // do deamon stuff
        
        
        // relax the system by sleeping for a little bit
        sleep(5);
    }
    
    $daemon->stop();
?>