<?php
/* vim: set expandtab tabstop=4 shiftwidth=4 foldmethod=marker: */
// +----------------------------------------------------------------------+
// | PHP Version 4 - 5                                                    |
// +----------------------------------------------------------------------+
// | Copyright (c) 1997-2003 The PHP Group                                |
// +----------------------------------------------------------------------+
// | This source file is subject to version 2.02 of the PHP license,      |
// | that is bundled with this package in the file LICENSE, and is        |
// | available at through the world-wide-web at                           |
// | http://www.php.net/license/2_02.txt.                                 |
// | If you did not receive a copy of the PHP license and are unable to   |
// | obtain it through the world-wide-web, please send a note to          |
// | license@php.net so we can mail you a copy immediately.               |
// +----------------------------------------------------------------------+
// | Author: Kevin van Zonneveld <kevin@vanzonneveld.net>                 |
// +----------------------------------------------------------------------+
// $Id: Daemon.php

/***
 * System_Daemon turns PHP-CLI scripts into daemons
 *
 * PHP version 5.1.0+
 *
 * LICENSE: This source file is subject to the New BSD license that is
 * available through the world-wide-web at the following URI:
 * http://www.opensource.org/licenses/bsd-license.php. If you did not receive
 * a copy of the New BSD License and are unable to obtain it through the web,
 * please send a note to license@php.net so we can mail you a copy immediately.
 *
 * @category    System
 * @package     System_Daemon
 * @author      Kevin van Zonneveld <kevin@vanzonneveld.net>
 * @license     http://www.opensource.org/licenses/bsd-license.php
 * @version     0.1
 * @link        http://kevin.vanzonneveld.net/pear/System_Daemon
 */

/***
 * System_Daemon. Create daemons with practicle functions like
 * $daemon->start()
 *
 * The shared variable space can be accessed with the tho methods:
 *
 * o void setVariable($name, $value)
 * o mixed getVariable($name)
 * $name must be a valid PHP variable name;
 * $value must be a variable or a serializable object.
 *
 * Requires PHP build with --enable-cli --with-pcntl --enable-shmop.
 * Only runs on *NIX systems, because Windows lacks of the pcntl ext.
 */

class Daemon {
    /****************************************************************************
    *** VARS
    ****************************************************************************/
    /***
     * The application name e.g.: logparser
     *
     * @var string
     * @access public
     */
    public $app_name;

    /***
     * The home dirpath e.g.: /usr/local/logparser. Defaults to __FILE__ dir
     *
     * @var string
     * @access public
     */
    public $app_dir;

    /***
     * The executeble daemon file, e.g.: logparser.php. Defaults to: SCRIPT_NAME basename
     *
     * @var string
     * @access public
     */
    public $app_executable;

    /***
     * Daemon description. Required for forging init.d script
     *
     * @var string
     * @access public
     */
    public $app_description;

    /***
     * Author name. Required for forging init.d script
     *
     * @var string
     * @access public
     */
    public $author_name;

    /***
     * Author email. Required for forging init.d script
     *
     * @var string
     * @access public
     */
    public $author_email;
  
    /***
     * The pid filepath , e.g.: /var/run/logparser.pid. Defaults to: /var/run/${app_name}.pid
     *
     * @var string
     * @access public
     */
    public $pid_filepath;

    /***
     * The log filepath , e.g.: /var/log/logparser_daemon.log. Defaults to: /var/log/${app_name}_daemon.log
     *
     * @var string
     * @access public
     */
    public $log_filepath;

    /***
     * The user id under which to run the process (default = root)
     *
     * @var string
     * @access public
     */
    public $uid = 0;

    /***
     * Wether the our daemon is being killed, you might want to include this in your loop
     *
     * @var boolean
     * @access public
     */
    public $is_dying = false;
    
    /***
     * The group id under which to run the process (default = root)
     *
     * @var string
     * @access public
     */
    public $gid = 0;

    /***
     * Kill daemon if it cannot assume the identity (uid + gid)
     *
     * @var string
     * @access public
     */
    public $die_on_identitycrisis = true;

    /***
     * Available log levels
     *
     * @var array
     * @access private
     */
    private $log_levels = array(
        0=> "debug",
        1=> "info",
        2=> "waring",
        3=> "critical",
        4=> "fatal"
    );

    /***
     * Keep track of passed signals
     *
     * @var array
     * @access private
     */
    private $signals = array();

    /***
     * The current process identifier
     *
     * @var integer
     * @access private
     */
    private $pid = 0;

    /***
     * Wether the current process is a forked child
     *
     * @var boolean
     * @access private
     */
    private $is_child = false;

    /***
     * Wether all the variables have been initialized
     *
     * @var boolean
     * @access private
     */
    private $is_initialized = false;

    /***
     * Cache return values of some functions for performance
     *
     * @var array
     * @access private
     */
    private $fnc_cache = array();


    /****************************************************************************
    *** SPECIAL METHODS
    ****************************************************************************/
    public function __construct($app_name)
    {
        $this->app_name = $app_name;

        //check the PHP configuration
        if (!defined('SIGHUP')){
            trigger_error('PHP is compiled without --enable-pcntl directive', E_USER_ERROR);
        }        
        
        ini_set("max_execution_time", "0");
        ini_set("max_input_time", "0");
        set_time_limit(0);
        ob_implicit_flush();

    }



    /****************************************************************************
    *** PUBLIC METHODS: DAEMON
    ****************************************************************************/
    /***
     * Sytem_Daemon::start()
     * Public method: spawn daemon process.
     *
     * @access public
     */
    public function start()
    {
        // initialize & check variables
        $this->_daemon_init();

        // become daemon
        $this->_daemon_become();

    }

    /***
     * Sytem_Daemon::stop()
     * Public method: stop daemon process.
     *
     * @access public
     */
    public function stop()
    {
        $this->_logger(1, "stopping ".$this->app_name." daemon", __FILE__, __CLASS__, __FUNCTION__, __LINE__);
        $this->_daemon_die();
    }


    /***
     * Sytem_Daemon::daemon_sig_handler()
     * Public method: signal handler function
     *
     * @access public
     */
    public function daemon_sig_handler( $signo )
    {
        // must be public or will throw error: Fatal error: Call to private method Daemon::daemon_sig_handler() from context '' 
        $this->_logger(0, $this->app_name." daemon received signal: ".$signo, __FILE__, __CLASS__, __FUNCTION__, __LINE__);
        switch ($signo) {
            case SIGTERM:
                // handle shutdown tasks
                if($this->is_child){
                    $this->_daemon_die();
                } else{
                    exit;
                }
                break;
            case SIGHUP:
                // handle restart tasks
                $this->_logger(1, $this->app_name." daemon received signal: restart", __FILE__, __CLASS__, __FUNCTION__, __LINE__);
                break;
            case SIGCHLD:
                $this->_logger(1, $this->app_name." daemon received signal: hold", __FILE__, __CLASS__, __FUNCTION__, __LINE__);
                while(pcntl_wait($status, WNOHANG OR WUNTRACED) > 0) {
                    usleep(1000);
                }
                break;
            default:
                // handle all other signals
        }
    }

    /****************************************************************************
    *** PUBLIC METHODS: GENERAL
    ****************************************************************************/

    /***
     * Sytem_Daemon::determineOS()
     * Public method: returns an array(main, distro, version) of the OS it's executed on
     *
     * @access public
     */
    public function determineOS()
    {
        if(!isset($this->fnc_cache[__FUNCTION__])){
            $osv_files = array(
                "RedHat"=>"/etc/redhat-release",
                "SuSE"=>"/etc/SuSE-release",
                "Mandrake"=>"/etc/mandrake-release",
                "Debian"=>"/etc/debian_version",
                "Ubuntu"=>"/etc/lsb-release"
            );

            if(strtoupper(substr(PHP_OS, 0, 3)) === 'WIN'){
                $main = "Windows";
                $distro = PHP_OS;
            } else{
                $main = php_uname('s');
                foreach($osv_files as $distro=>$osv_file){
                    if(file_exists($osv_file)){
                        $version = trim(file_get_contents($osv_file));
                        break;
                    }
                }
            }

            $this->fnc_cache[__FUNCTION__] = compact("main", "distro", "version");
        }

        return $this->fnc_cache[__FUNCTION__];
    }    
    
    /***
     * Sytem_Daemon::initd_write()
     * Public method: writes an: 'init.d' script on the filesystem
     *
     * @access public
     */
    public function initd_write(){
        $initd_filepath = $this->initd_filepath();
        if(!$initd_filepath){
            return false;
        }
        
        $initd = $this->initd();
        if(!$initd){
            return false;
        }
        
        if(!file_exists( ( $initd_filepath ) )){
            if(!file_put_contents($initd_filepath, $initd)){
                return false;
            }
            
            if(!chmod($initd_filepath, 0777)){
                return false;
            }
            return true;
        }
        return false;
    }
    
    /***
     * Sytem_Daemon::initd_filepath()
     * Public method: returns an: 'init.d' script path as a string. for now only debian & ubuntu
     *
     * @access public
     */
    public function initd_filepath(){
        
        $initd_filepath = false;
        
        // collect OS information
        list($main, $distro, $version) = array_values($this->determineOS());
        
        // where to collect the skeleton (template) for our init.d script
        switch (strtolower($distro)){
            case "debian":
            case "ubuntu":
                // here it is for debian systems
                $initd_filepath = "/etc/init.d/".$this->app_name;
            break;
            default:
                // not supported yet
                $this->_logger(2, "skeleton retrieval for OS: ".$distro." currently not supported ", __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            break;
        }
        
        return $initd_filepath;
    }
    
    /***
     * Sytem_Daemon::initd()
     * Public method: returns an: 'init.d' script as a string. for now only debian & ubuntu
     *
     * @access public
     */
    public function initd(){
        // initialize & check variables
        $this->_daemon_init();

        // sanity
        $daemon_filepath = $this->app_dir."/".$this->app_executable;
        if(!file_exists($daemon_filepath)){
            $this->_logger(3, "unable to forge skeleton for non existing daemon_filepath: ".$daemon_filepath.", try setting a valid app_dir or app_executable", __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }
        if(!$this->author_name){
            $this->_logger(3, "unable to forge skeleton for non existing author_name: ".$this->author_name."", __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }
        if(!$this->author_email){
            $this->_logger(3, "unable to forge skeleton for non existing author_email: ".$this->author_email."", __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }
        if(!$this->app_description){
            $this->_logger(3, "unable to forge skeleton for non existing app_description: ".$this->app_description."", __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }

        // collect OS information
        list($main, $distro, $version) = array_values($this->determineOS());

        // where to collect the skeleton (template) for our init.d script
        switch (strtolower($distro)){
            case "debian":
            case "ubuntu":
                // here it is for debian systems
                $skeleton_filepath = "/etc/init.d/skeleton";
            break;
            default:
                // not supported yet
                $this->_logger(2, "skeleton retrieval for OS: ".$distro." currently not supported ", __FILE__, __CLASS__, __FUNCTION__, __LINE__);
                return false;
            break;
        }

        // open skeleton
        if(!file_exists($skeleton_filepath)){
            $this->_logger(2, "skeleton file for OS: ".$distro." not found at: ".$skeleton_filepath, __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;
        } elseif($skeleton = file_get_contents($skeleton_filepath)){
            // skeleton opened, set replace vars
            switch (strtolower($distro)){
                default:
                    $replace = array(
                        "Foo Bar" => $this->author_name,
                        "foobar@baz.org" => $this->author_email,
                        "daemonexecutablename" => $this->app_name,
                        "Example" => $this->app_name,
                        "skeleton" => $this->app_name,
                        "/usr/sbin/\$NAME" => $daemon_filepath,
                        "Description of the service"=> $this->app_description,
                        " --name \$NAME" => "",
                        "--options args" => "",
                        "# Please remove the \"Author\" lines above and replace them" => "",
                        "# with your own name if you copy and modify this script." => ""
                    );
                break;
            }

            // replace skeleton placeholders with actual daemon information
            $skeleton = str_replace( array_keys($replace), array_values($replace), $skeleton );

            // return the forged init.d script as a string
            return $skeleton;
        }
    }



    /****************************************************************************
    *** PRIVATE METHODS: DAEMON
    ****************************************************************************/

    /***
     * Sytem_Daemon::_daemon_init()
     * Private method: put the running script in background
     *
     * @access private
     */
    function _daemon_init() {
        if($this->is_initialized){
            return true;
        }

        $this->is_initialized = true;

        if( !$this->_strisunix($this->app_name) ){
            $safe_name = $this->_strtounix($this->app_name);
            $this->_logger(4, "'".$this->app_name."' is not a valid daemon name, try using '".$safe_name."' instead", __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;
        } else{
            $this->_logger(1, "starting ".$this->app_name." daemon", __FILE__, __CLASS__, __FUNCTION__, __LINE__);
        }
        if(!$this->pid_filepath){
            $this->pid_filepath = "/var/run/".$this->app_name.".pid";
        }
        if(!$this->log_filepath){
            $this->log_filepath = "/var/log/".$this->app_name."_daemon.log";
        }
        
        $this->pid = 0;
        $this->is_child = false;
        if(!is_numeric($this->uid)) {
            $this->_logger(4, "".$this->app_name." daemon has invalid uid: ".$this->uid."", __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }
        if(!is_numeric($this->gid)) {
            $this->_logger(4, "".$this->app_name." daemon has invalid gid: ".$this->gid."", __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }
        if(!$this->app_dir){
            $this->app_dir = dirname(__FILE__);
        }
        if(!$this->app_executable){
            $this->app_executable = basename($_SERVER["SCRIPT_FILENAME"]);
        }

        if(!is_dir($this->app_dir)){
            $this->_logger(4, "".$this->app_name." daemon has invalid app_dir: ".$this->app_dir."", __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;
        }
    }

    /***
     * Sytem_Daemon::_daemon_become()
     * Private method: put the running script in background
     *
     * @access private
     */
    function _daemon_become() {

        // important for daemons
        declare(ticks = 1);

        // setup signal handlers
        

        
        pcntl_signal(SIGCONT, array($this, "daemon_sig_handler"));
        pcntl_signal(SIGALRM, array($this, "daemon_sig_handler"));
        pcntl_signal(SIGINT,  array($this, "daemon_sig_handler"));
        pcntl_signal(SIGABRT, array($this, "daemon_sig_handler"));
        
        pcntl_signal(SIGTERM, array($this, "daemon_sig_handler"));
        pcntl_signal(SIGHUP,  array($this, "daemon_sig_handler"));
        pcntl_signal(SIGUSR1, array($this, "daemon_sig_handler"));
        pcntl_signal(SIGCHLD, array($this, "daemon_sig_handler"));

        // allowed?
        if ( $this->_daemon_isrunning() ){
            $this->_logger(4, "".$this->app_name." daemon is still running. exiting", __FILE__, __CLASS__, __FUNCTION__, __LINE__);
        }

        // fork us!
        if ( !$this->_daemon_fork() ){
            $this->_logger(4, "".$this->app_name." daemon was unable to fork", __FILE__, __CLASS__, __FUNCTION__, __LINE__);
        }

        // assume identity
        if(!posix_setuid($this->uid) || !posix_setgid($this->gid)){
            $lvl = ($this->die_on_identitycrisis ? 4 : 3);
            $this->_logger($lvl, "".$this->app_name." daemon was unable assume identity (uid=".$this->uid.", gid=".$this->gid.")", __FILE__, __CLASS__, __FUNCTION__, __LINE__);
        }

        // additional PID succeeded check
        if(!is_numeric($this->pid) || $this->pid < 1){
            $this->_logger(4, "".$this->app_name." daemon didn't have a valid pid: '".$this->pid."'", __FILE__, __CLASS__, __FUNCTION__, __LINE__);
        } else{
            if(!file_put_contents($this->pid_filepath, $this->pid)){
                $this->_logger(4, "".$this->app_name." daemon was unable to write to pidfile: ".$this->pid_filepath."", __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            }
        }

        // change dir & umask
        @chdir($this->app_dir);
        @umask(0);
    }

    /***
     * Sytem_Daemon::_daemon_isrunning()
     * Private method: check if a previous process with same pidfile was already running
     *
     * @access private
     */
    function _daemon_isrunning() {
        if(!file_exists($this->pid_filepath)) return false;
        $pid = @file_get_contents($this->pid_filepath);

        if ($pid !== false) {
            if( !posix_kill(intval($pid), 0) ){
                // not responding so unlink pidfile
                @unlink($this->pid_filepath);
                $this->_logger(2, "".$this->app_name." daemon orphaned pidfile found and removed: ".$this->pid_filepath, __FILE__, __CLASS__, __FUNCTION__, __LINE__);
                return false;
            } else{
                return true;
            }
        } else {
            return false;
        }
    }

    /***
     * Sytem_Daemon::_daemon_fork()
     * Private method: fork process and kill parent process, the heart of the 'daemonization'
     *
     * @access private
     */
    function _daemon_fork()
    {
        $this->_logger(0, "forking ".$this->app_name." daemon", __FILE__, __CLASS__, __FUNCTION__, __LINE__);

        $pid = pcntl_fork();
        if ( $pid == -1 ) {
            // error
            $this->_logger(3, "".$this->app_name." daemon could not be forked", __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            return false;
        } else if ($pid) {
             // parent
            $this->_logger(0, "ending ".$this->app_name." parent process", __FILE__, __CLASS__, __FUNCTION__, __LINE__);
            exit();
        } else {
            // children
            $this->is_child = true;
            $this->is_dying = false;
            $this->pid = posix_getpid();
            return true;
        }
    }

    /***
     * Sytem_Daemon::_daemon_whatiam()
     * Private method: return what the current process is: child or parent
     *
     * @access private
     */
    private function _daemon_whatiam()
    {
        return ($this->is_child?"child":"parent");
    }

    /***
     * Sytem_Daemon::_daemon_die()
     * Private method: kill the daemon
     *
     * @access private
     */
    private function _daemon_die()
    {
        if($this->is_dying != true){
            $this->is_dying = true;
            if($this->is_child && file_exists($this->pid_filepath)){
                @unlink($this->pid_filepath);
            }
            exit();
        }
    }



    /****************************************************************************
    *** PRIVATE METHODS: GENERAL
    ****************************************************************************/

    /***
     * Sytem_Daemon::_strisunix()
     * Private method: check if a string has a unix proof format (stripped spaces, special chars, etc)
     *
     * @access private
     */
    private function _strisunix( $str )
    {
        return preg_match('/^[a-z0-9_]+$/', $str);
    }

    /***
     * Sytem_Daemon::_strtounix()
     * Private method: convert a string to a unix proof format (strip spaces, special chars, etc)
     *
     * @access private
     */
    private function _strtounix( $str )
    {
        return preg_replace('/[^0-9a-z_]/', '', strtolower($str));
    }

    /***
     * Sytem_Daemon::_logger()
     * Private method: log a string according to error levels specified in array: log_levels (4 is fatal)
     *
     * @access private
     */
    private function _logger($level, $str, $file = false, $class = false, $function = false, $line = false)
    {
        if( $file == false || $class == false || $function == false || $line == false ){
            // saves resources if arguments are passed.
            // but by using debug_backtrace() it still works if someone forgets to pass them
            $dbg_bt = @debug_backtrace();

            $class = (isset($dbg_bt[1]["class"])?$dbg_bt[1]["class"]:"");
            $function = (isset($dbg_bt[1]["function"])?$dbg_bt[1]["function"]:"");
            $file = $dbg_bt[0]["file"];
            $line = $dbg_bt[0]["line"];
        }

        $str_pid = "from[".$this->_daemon_whatiam()."".posix_getpid()."] ";
        $str_level = $this->log_levels[$level];
        $log_line = str_pad($str_level."", 8, " ", STR_PAD_LEFT)." " .$str_pid." : ".$str; 
        //echo $log_line."\n";
        file_put_contents($this->log_filepath, $log_line."\n", FILE_APPEND);
        
        if($level == 4){
            $this->_daemon_die();
        }
    }
}


?>