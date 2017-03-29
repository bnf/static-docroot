<?php
namespace Bnf\StaticDocroot;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Composer\Util\Filesystem;

class Plugin implements PluginInterface, EventSubscriberInterface
{
    protected $composer;
    protected $io;

    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    public static function getSubscribedEvents()
    {
        return array(
            ScriptEvents::PRE_AUTOLOAD_DUMP  => 'addIncludeFile',

        );
    }

    public function addIncludeFile(Event $composerEvent)
    {
        $filesystem = new FileSystem();

        $filepath = $this->composer->getConfig()->get('vendor-dir') . '/bnf/static-docroot-include.php';
        $contents = sprintf($this->getFileTemplate(), $this->getWebDir());

        if (@file_put_contents($filepath, $contents, 0664) !== false) {
            $rootPackage = $this->composer->getPackage();
            $autoloadDefinition = $rootPackage->getAutoload();
            $autoloadDefinition['files'][] = $filepath;
            $rootPackage->setAutoload($autoloadDefinition);
            $this->io->writeError('<info>Registered bnf/static-docroot in composer autoload definition</info>');
        } else {
            $this->io->writeError('<error>Could not dump bnf/static-docroot autoload include file</error>');
        }
    }

    private function getWebDir()
    {
        $extra = $this->composer->getPackage()->getExtra();

        if (isset($extra['bnf/static-docroot']['web-dir'])) {
            return $extra['bnf/static-docroot']['web-dir'];
        }

        if (isset($extra['typo3/cms']['web-dir'])) {
            return $extra['typo3/cms']['web-dir'];
        }

        return 'web';
    }

    private function getFileTemplate()
    {
        $fileTemplate = <<<'PHP'
<?php

/**
 * statically render the realpath of the DOCUMENT_ROOT to prevent realpath cache
 */

/* TODO: Add support for nested vendor dir (e.g. vendor-dir = 'contrib/composer') */
$basedir = dirname(dirname(__DIR__));
$webdir = '%s';

$setenv = function ($name, $value = null) {
    // If PHP is running as an Apache module and an existing
    // Apache environment variable exists, overwrite it
    if (function_exists('apache_getenv') && function_exists('apache_setenv') && apache_getenv($name)) {
        apache_setenv($name, $value);
    }

    if (function_exists('putenv')) {
        putenv("$name=$value");
    }

    $_ENV[$name] = $value;
    $_SERVER[$name] = $value;
};

// Statically set DOCUMENT_ROOT to the realpath of the current release.
// This is to prevent symlink related realpath cache issues (using an old
// release, although a new one is available) and prevents using files
// from different releases during one request.

$setenv('DOCUMENT_ROOT', $basedir . $webdir);
$setenv('SCRIPT_FILENAME', $basedir . $webdir . $_SERVER['SCRIPT_NAME']);

unset($setenv);
unset($basedir);
unset($webdir);

PHP;
        return $fileTemplate;
    }
}