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

        $rootPathCode = $filesystem->findShortestPathCode(
            dirname($filepath),
            $this->getBaseDir() . '/' . $this->getWebDir(),
            true
        );

        $contents = sprintf($this->getFileTemplate(), $rootPathCode);

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

    protected function getBaseDir()
    {
        $config = $this->composer->getConfig();

        $reflectionClass = new \ReflectionClass($config);
        $reflectionProperty = $reflectionClass->getProperty('baseDir');
        $reflectionProperty->setAccessible(true);

        return $reflectionProperty->getValue($config);
    }

    private function getFileTemplate()
    {
        $fileTemplate = <<<'PHP'
<?php

/**
 * statically render the realpath of the DOCUMENT_ROOT to prevent realpath cache
 */

$root = %s;

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

if (isset($_SERVER['DOCUMENT_ROOT']) && $_SERVER['DOCUMENT_ROOT'] !== '') {
    $setenv('DOCUMENT_ROOT', $root);
    if (substr($_SERVER['SCRIPT_FILENAME'], 0, length($_SERVER['DOCUMENT_ROOT'])) === $_SERVER['DOCUMENT_ROOT']) {
        $setenv('SCRIPT_FILENAME', $root . $_SERVER['SCRIPT_NAME']);
    }
}

unset($setenv);
unset($root);

PHP;

        return $fileTemplate;
    }
}
