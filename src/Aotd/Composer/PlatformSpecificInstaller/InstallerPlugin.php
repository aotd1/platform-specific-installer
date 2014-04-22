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
use Composer\Script\Event;
use Composer\Package\Package;
use Composer\Package\Version\VersionParser;


class InstallerPlugin {

    /**
     * @var \Composer\Composer
     */
    public static $composer;

    /**
     * @var \Composer\IO\IOInterface
     */
    public static $io;

    public static function getInstance($event)
    {
        self::$composer = $event->getComposer();
        self::$io = $event->getIO();
    }

    public static function run(Event $event)
    {
        self::getInstance($event);
        $extra = self::$composer->getPackage()->getExtra();

        if (empty($extra['platform-specific-require']))
            return;

        $unresolved = array();
        foreach ($extra['platform-specific-require'] as $name => $variants) {
            if (!self::tryInstall($variants)) {
                $unresolved[] = $name;
            }
        }

        if (!empty($unresolved)) {
            self::$io->write('<error>Your requirements could not be resolved for current OS and/or processor architecture.</error>');
            self::$io->write("\n  Unresolved platform-specific packages:");
            foreach ($unresolved as $name)
                self::$io->write("    - $name");
            $event->stopPropagation();
        }
    }

    protected static function tryInstall($variants)
    {
        foreach ($variants as $variant) {
            if (!empty($variant['architecture']) && $variant['architecture'] !== self::getArchitecture())
                continue;

            if (!empty($variant['os']) && $variant['os'] !== self::getOS())
                continue;

            reset($variant);
            $name = key($variant);
            $version = $variant[$name];

            var_dump($name, $version);
            self::insertPackage(
                self::$composer->getPackage(),
                new Link($version, $name)
            );
            return true;
        }

        return false;
    }

    protected static function insertPackage(RootPackageInterface $package, Link $link)
    {
        $downloadManager = self::$composer->getDownloadManager();
        $downloadManager->download($package, $link->getTarget());
        //$package->setRequires(array_merge($package->getRequires(), array($link)));
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