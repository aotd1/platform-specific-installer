<?php

namespace Aotd\Composer\PlatformSpecificInstaller;

use Composer\Composer;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Plugin\PluginInterface;
use Composer\Plugin\PluginEvents;
use Composer\Package\Link;
use Composer\Package\RootPackageInterface;
use Composer\Plugin\CommandEvent;

class InstallerPlugin implements PluginInterface, EventSubscriberInterface {

    /**
     * @var Composer
     */
    protected $composer;

    /**
     * @var IOInterface
     */
    protected $io;

    public function activate(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;
        $this->run();
    }

    public static function getSubscribedEvents()
    {
//        return array(
//            PluginEvents::COMMAND => 'run',
//        );
    }

    /**
     * Operating system depended installation of packages
     */
    public function run(/* CommandEvent $event */)
    {
//        if ( !in_array($event->getCommandName(), array('install', 'update')) )
//            return;

        $extra = $this->composer->getPackage()->getExtra();
        if (empty($extra['platform-specific-require']))
            return;

        $unresolved = array();
        foreach ($extra['platform-specific-require'] as $name => $variants) {
            if (!$this->tryInstall($variants)) {
                $unresolved[] = $name;
            }
        }

        if (!empty($unresolved)) {
            $this->io->write('<error>Your requirements could not be resolved for current OS and/or processor architecture.</error>');
            $this->io->write("\n  Unresolved platform-specific packages:");
            foreach ($unresolved as $name)
                $this->io->write("    - $name");
//          $event->stopPropagation();
        }

    }

    protected function tryInstall($variants)
    {
        foreach ($variants as $variant) {
            if (!empty($variant['architecture']) && $variant['architecture'] !== self::getArchitecture())
                continue;

            if (!empty($variant['os']) && $variant['os'] !== self::getOS())
                continue;

            reset($variant);
            $name = key($variant);
            $version = $variant[$name];

            self::insertPackage(
                $this->composer->getPackage(),
                new Link($version, $name)
            );
            return true;
        }

        return false;
    }

    protected static function insertPackage(RootPackageInterface $package, Link $link)
    {
        $package->setRequires(array_merge($package->getRequires(), array($link)));
    }

    /**
     * Returns the Operating System.
     *
     * @return string OS, e.g. macosx, freebsd, windows, linux.
     */
    public static function getOS()
    {
        $uname = strtolower(php_uname());

        if (strpos($uname, "darwin") !== false) {
            return 'macosx';
        } elseif (strpos($uname, "win") !== false) {
            return 'windows';
        } elseif (strpos($uname, "freebsd") !== false) {
            return 'freebsd';
        } elseif (strpos($uname, "linux") !== false) {
            return 'linux';
        } else {
            return 'undefined';
        }
    }

    /**
     * Returns the Architecture.
     *
     * @return string BitSize, e.g. i386, x64.
     */
    public static function getArchitecture()
    {
        switch (PHP_INT_SIZE) {
            case 4: return 'i386';
            case 8: return 'x64';
            default: return 'undefined';
        }
    }

} 