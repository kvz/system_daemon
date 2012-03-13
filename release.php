#!/usr/bin/php
<?php
error_reporting(E_ALL ^ E_DEPRECATED);
/**
 * print_r shortcut
 */
function pr() {
    $args = func_get_args();

    if (php_sapi_name() !=='cli') {
        echo '<pre>'."\n";
    }
    foreach($args as $arg) {
        print_r($arg);
    }
    if (php_sapi_name() !=='cli') {
        echo '</pre>'."\n";
    }
}

/**
 * print_r & die shortcut
 */
function prd() {
    $args = func_get_args();
    call_user_func_array('pr', $args);
    die();
}

if (!defined('DIR_EGGSHELL')) {
    $file   = 'EggShell.php';
    $lookIn = array(
        (@$_ENV['HOME'] ? @$_ENV['HOME'] : '/home/kevin').'/workspace/eggshell/',
        '/truecode/eggshell/',
         dirname(__FILE__).'/vendors/eggshell/',
    );
    foreach($lookIn as $dir) {
        if (is_dir($dir) && file_exists($dir.$file)) {
            define('DIR_EGGSHELL', $dir);
            break;
        }
    }
    if (!defined('DIR_EGGSHELL')) {
        trigger_error($file.' not found in either: '.implode(', ', $lookIn), E_USER_ERROR);
    }
}
require_once DIR_EGGSHELL.$file;

if (!defined('DIR_SCM')) {
    $file   = 'Scm.php';
    $lookIn = array(
        (@$_ENV['HOME'] ? @$_ENV['HOME'] : '/home/kevin').'/workspace/scm/',
        '/truecode/scm/',
         dirname(__FILE__).'/vendors/scm/',
    );
    foreach($lookIn as $dir) {
        if (is_dir($dir) && file_exists($dir.$file)) {
            define('DIR_SCM', $dir);
            break;
        }
    }
    if (!defined('DIR_SCM')) {
        trigger_error($file.' not found in either: '.implode(', ', $lookIn), E_USER_ERROR);
    }
}
require_once DIR_SCM.$file;

if (!include('PEAR/PackageFileManager2.php')) {
    die('Please: pear install -f PEAR_PackageFileManager-2');
}


Class Release extends EggShell {
    public $apiStability;
    public $apiVersion;
    public $version;
    public $dir;
    public $name;

    public $Pack;
    public $Scm;


    /**
     * Contstructor. Options are passed to every Class contstructor
     * and hence available troughout the entire system.
     *
     * To avoid collission, That's why they're prefixed with scope.
     *
     * @param array $options
     */
    public function  __construct($options = array()) {
        $this->className = get_class($this);

        // Take care of recursion when there are ++++ levels of inheritance
        parent::__construct($options);
        // Get direct parent defined options
        $parentVars    = @get_class_vars(@get_parent_class(__CLASS__));
        // Override with own defined options
        $this->_options = $this->merge((array)@$parentVars['_options'], $this->_options);
        // Override with own instance options
        $this->_options = $this->merge($this->_options, $options);

        $this->dir     = $this->_options['app-root'];
        $this->name    = $this->_options['package-name'];

        PEAR::setErrorHandling(PEAR_ERROR_DIE);
        $this->Pack = new PEAR_PackageFileManager2();
        $this->Scm  = new Scm($this->dir);
        if (!$this->Scm->scm) {
            $this->err('Could not establish SCM');
        }
    }

    public function setVersion($version) {
        $this->version = $version;
        // Own starts here
        if (!$this->version || false === strpos($this->version, 'v')) {
            return $this->err('Need to specify a release like: v0.9.2-beta');
        }
        if (count($parts = explode('-', $this->version)) > 1) {
            $this->apiStability = $parts[1];
        } else {
            $this->apiStability = 'stable';
        }
        $this->apiVersion = str_replace('v', '', $parts[0]);
    }


    public function changelog() {
        $after = $this->_tagBefore($this->version);

        $scmOptions = array(
            'duplicates' => false,
            'onewords' => false,
            'after' => $after,
            'until' => 'HEAD',
        );

        $logs = $this->Scm->logs($scmOptions);
        $cl = '';
        foreach($logs as $log) {
            if (@$log['verbose'] > 0) {
                continue;
            }
            #$cl .= ' * ' . $log['date'] . ' | '.str_pad($log['weight'], 3, ' ', STR_PAD_LEFT) . ' | '. $log['comment']."\n";
            $cl .= ' * ' . $log['comment']."\n";
        }
        file_put_contents($this->dir.'/docs/CHANGELOG', $cl);
        echo $cl;
    }

    protected function _tagBefore($leadTag = null) {
        if ($leadTag === null) {
            $leadTag = $this->version;
        }

        $tags   = $this->Scm->tags();
        // Use last tag as starting point
        $after = end($tags);
        foreach ($tags as $i=>$tag) {
            if ($tag === $leadTag) {
                // Tag already exist? Use tag before That, as starting point
                if (isset($tags[($i-1)])) {
                    $after = $tags[($i-1)];
                } else {
                    $after = null;
                }
                break;
            }
        }
        return $after;
    }

    protected function _histFile($file, $commit, $losefirstline = false) {
        $buf = $this->Scm->getFile($file, $commit);

        if ($losefirstline) {
            $lines = explode("\n", $buf);
            array_shift($lines);
            $buf = join("\n", $lines);
        }

        return $buf;
    }

    public function updateXML($tag, $firsttime = false) {
        $opts = $this->_opts($tag);
        $e = $this->Pack->setOptions($opts);

        // Oddly enough, this is a PHP source code package...
        $this->Pack->setPackageType('php');
        // Package name, summary and longer description
        $this->Pack->setPackage($this->name);
        $this->Pack->setSummary($opts['summary']);
        $this->Pack->setDescription($opts['description']);
        // The channel where this package is hosted. Since we're installing from a local
        // downloaded file rather than a channel we'll pretend it's from PEAR.
        $this->Pack->setChannel('pear.php.net');

        foreach ($opts['maintainers'] as $line) {
            list($role, $nick, $mname, $email, $active) = explode(',', $line);
            if ($firsttime) $this->Pack->addMaintainer($role, $nick, $mname, $email, $active);
        }

        $this->Pack->setNotes($opts['notes']);
        // Add any known dependencies such as PHP version, extensions, PEAR installer
        if ($firsttime) $this->Pack->setPhpDep('5.1.2'); // spl_autoload_register
        if ($firsttime) $this->Pack->setPearinstallerDep('1.4.0');
        //if ($firsttime) $this->Pack->addDependency('pcntl', '', 'has', 'ext', true);
        //if ($firsttime) $this->Pack->addDependency('posix', '', 'has', 'ext', true);
        $this->Pack->setOSInstallCondition('(*ix|*ux|darwin*|*BSD|SunOS*)');
        if ($firsttime) $this->Pack->addPackageDepWithChannel('optional', 'Log', 'pear.php.net', '1.0');


        // Other info, like the Lead Developers. license, version details
        // and stability type
        $this->Pack->setLicense('New BSD License',
            'http://opensource.org/licenses/bsd-license.php');
        $this->Pack->setAPIVersion($this->apiVersion);
        $this->Pack->setAPIStability($this->apiStability);
        $this->Pack->setReleaseVersion($this->apiVersion);
        $this->Pack->setReleaseStability($this->apiStability);
        // Add this as a release, and generate XML content
        $this->Pack->addRelease();

        $this->Pack->generateContents();

        if (@$this->_options['debug']) {
            return $this->Pack->debugPackageFile();
        } else {
            return $this->Pack->writePackageFile();
        }
    }

    protected function _opts($tag) {
        $summary     = $this->_histFile('docs/SUMMARY', $tag);
        $description = $this->_histFile('docs/DESCRIPTION', $tag);
        $notes       = $this->_histFile('docs/NOTES', $tag, true);
        $notes       = $this->name.' ' . $tag . ' ' . "\n".$notes;

        $maintainers = explode("\n", $this->read($this->dir.'/docs/MAINTAINERS'));

        $options = array(
            'package'           => $this->name,
            'summary'           => $summary,
            'maintainers'       => $maintainers,
            'version'           => $this->apiVersion,
            'state'             => $this->apiStability,
            'description'       => $description,
            'notes'             => $notes,
            'filelistgenerator' => 'file',
            'ignore'            => array(
                'package2.php',
                'package.php',
                'package.xml',
                'catalog.xml',
                '*.tgz',
                '.svn',
                '.git',
                'test.php',
                'release.php',
                'gitty*',
                '.project',
                'nbproject/',
                'vendors/',
                'docs',
                'tools/',
            ),
            'simpleoutput'      => true,
            'clearcontents'     => true,
            'baseinstalldir'    => '/',
            'packagedirectory'  => $this->dir,
            'packagefile'       => 'package.xml',
            'dir_roles'         => array(
                'docs' => 'doc',
                'examples' => 'doc',
                'tests' => 'test',
                'data' => 'data',
            ),
            'roles'             => array(
                '*' => 'php',
            ),
        );
        return $options;
    }

    public function bake($sleep = true) {
        $this->changelog();
//        if ($sleep) {
//            $this->info('Press CTRL+C Now! if you didn\'t make a bullet list in docs/NOTES');
//            sleep(5);
//        }

        // Make tag now so updateXML can find it
        // just don't push yet
        $this->exe('cd %s && git commit -am "Preparing to release %s" ', $this->dir, $this->version);
        $this->Scm->makeTag($this->version, 'HEAD', 'Released new version', false);

        @unlink($this->dir.'/package.xml');
        $firsttime = true;
        $tags = $this->Scm->tags();
        usort($tags, 'version_compare');

        foreach($tags as $tag) {
            $this->updateXML($tag, $firsttime);
            $firsttime = false;
        }

        $this->exe('cd %s && pear package && mv *.tgz ./packages/', $this->dir);
        $this->exe('cd %s && git add ./packages/*.tgz', $this->dir);
        $this->exe('cd %s && git commit -am "Releasing %s" ', $this->dir, $this->version);

        // Overwrite full tag && push
        $this->Scm->makeTag($this->version, 'HEAD', 'Released new version', true);
    }
}


$Release = new Release(array(
    'package-name' => 'System_Daemon',
    'log-file' => '/var/log/release.log',
    'log-file-level' => 'info',
    'app-root' => dirname(__FILE__),
));

switch(@$argv[1]) {
    case 'changelog':
        $Release->{$argv[1]}();
        break;
    case 'bake':
        // @todo remove false
        $Release->setVersion(@$argv[2]);
        $Release->{$argv[1]}();
        break;
    default:

        if (@$argv[2] === 'all') {
            $tags = $Release->Scm->tags();
        } else {
            $tags = (array)(@$argv[2]);
        }

        if (!count($tags)) {
            $Release->err('No tags specified?');
            exit(1);
        }

        foreach ($tags as $tag) {
            $Release->setVersion($tag);

            if (!method_exists($Release, @$argv[1])) {
                $Release->err('Method %s does not exist', @$argv[1]);
            } else {
                $Release->{$argv[1]}();
            }
        }

        break;
}
