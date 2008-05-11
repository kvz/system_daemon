#!/usr/bin/php -q
<?php
/* vim: set noai expandtab tabstop=4 softtabstop=4 shiftwidth=4: */
/**
 * Script to generate changelog file
 *
 * Relies on svn2cl
 * Echo's on STDOUT
 * 
 * PHP version 5 
 * 
 * @category  System
 * @package   System_Daemon
 * @author    Kevin <kevin@vanzonneveld.net>
 * @copyright 2008 Kevin van Zonneveld (http://kevin.vanzonneveld.net)
 * @license   http://www.opensource.org/licenses/bsd-license.php New BSD Licence
 * @version   SVN: Release: $Id$
 * @link      http://trac.plutonia.nl/projects/system_daemon
 */

$workspace_dir        = realpath(dirname(__FILE__)."/..");
$cmd_reqs             = array();
$cmd_reqs["svn2cl"]   = "subversion-tools";
$map_authors          = array();
$map_authors["kevin"] = "Kevin van Zonneveld <kevin@vanzonneveld.net>";

// check if commands are available
foreach ($cmd_reqs as $cmd=>$package) {
    exec("which ".$cmd, $o, $r);
    if ($r) {
        echo $cmd." is not available. ";
        echo "Please first install the ".$package;            
        die("\n");
    }
}

if (isset($argv[1]) && is_numeric($argv[1])) {
    $rev = "--revision=HEAD:".$argv[1];
    
}

// collect data
$cmd  = "";
$cmd .= "svn2cl -i --stdout --group-by-day ".$rev." --linelen=90000 ".$workspace_dir;
exec($cmd, $o, $r);
if ($r) {
    die("Executing: ".$cmd." failed miserably\n");
}

// divide blocks
$blocks    = array();
$cur_block = false;
foreach ($o as $i=>$line_raw) {
    if (preg_match('/^(\d{4}-\d{2}-\d{2})(\s*)(.*)/', $line_raw, $match)) {        
        // prepare new block
        $date      = trim($match[1]);
        $author    = trim($match[3]);
        $author    = str_replace(array_keys($map_authors), 
            array_values($map_authors), $author);        
        $cur_block = $date . " ". $author;
                
        // don't record the date header itsself
        continue; 
    }    
    
    $line = trim($line_raw);
    
    // don't record empty lines
    if (!$line) continue;
    if (!$cur_block) continue;
    
    // extract comment + revision
    preg_match('/^[^\[]+(\[r\d+\])(.*)/', $line, $match);
    $revision = trim($match[1]);
    $comment  = trim($match[2]);
    if (!$revision && $old_revision) {
        // fallback to old revision
        $revision = $old_revision;
    }
    if (!$comment) {
        $comment = $line;
    }
    
    // remove the file which the comment is about
    $comment = preg_replace('/^([^:])+:(\s+)/', '', $comment);
    $comment = trim($comment);
    
    // don't record empty lines
    if (!$comment) continue;
    
    if (!substr($comment, 0, 7) == "http://") {
        $comment = ucfirst($comment);
    }
    
    // record what's left
    $blocks[$cur_block][$comment][] = $revision;
    
    // nescesary for fallback in case newlines are used for commit comments
    $old_revision = $revision;
}

// simplify blocks
foreach ($blocks as $date=>$block_arr) {
    if (isset($blocks[$date]) && is_array($blocks[$date])) {
        foreach ($blocks[$date] as $comm=>$revs) {
            $blocks[$date][$comm] = reset($revs);
        }
    }
}

// print blocks
$cnt = 1;
foreach ($blocks as $date=>$block_arr) {
    if ($cnt != 1) {
        echo "\n";
    }
    echo $date."\n\n";
    foreach ($blocks[$date] as $comm=>$rev) {
        echo "        ".$rev." ".$comm."\n";
    }
    $cnt++;
}
echo "\n";
?>