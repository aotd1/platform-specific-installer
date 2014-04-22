<?php

namespace Aotd\Composer\PlatformSpecificInstaller;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Installer\InstallationManager;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\Repository\WritableRepositoryInterface;
use Composer\Package\PackageInterface;
use Composer\Script\Event;
use Composer\Package\Package;


class InstallerPlugin {

    /**
     * @var Composer $composer
     */
    public static $composer;

    /**
     * @var IOInterface $io
     */
    public static $io;

    /**
     * @var InstallationManager $installer
     */
    public static $installer;

    /**
     * @var WritableRepositoryInterface $localRepo
     */
    public static $localRepo;

    /**
     * @var Package[] platform-specific packages to install
     */
    public static $toInstall = array();

    public static function init(Event $event)
    {
        self::$composer = $event->getComposer();
        self::$io = $event->getIO();
        self::$installer = $event->getComposer()->getInstallationManager();
        self::$localRepo = $event->getComposer()->getRepositoryManager()->getLocalRepository();

        //@TODO: refactor it all to use composer.lock file, to track updated platform-specific packages
        self::$toInstall = array();
        $extra = self::$composer->getPackage()->getExtra();

        if (empty($extra['platform-specific-require']))
            return false;

        $unresolved = array();
        foreach ($extra['platform-specific-require'] as $name => $variants) {
            $package = self::createPlatformSpecificPackage($name, $variants);
            if ($package) {
                self::$toInstall[] = $package;
            } else {
                $unresolved[] = $name;
            }
        }

        if (!empty($unresolved)) {
            self::$io->write('<error>Your requirements could not be resolved for current OS and/or processor architecture.</error>');
            self::$io->write("\n  Unresolved platform-specific packages:");
            foreach ($unresolved as $name) {
                self::$io->write("    - $name");
            }
        }

        return true;
    }

    public static function install(Event $event)
    {

        if (!self::init($event)) {
            return;
        }

        $notInstalled = 0;
        if (!empty(self::$toInstall)) {
            self::$io->write('<info>Installing platform-specific dependencies</info>');
            foreach (self::$toInstall as $package) {
                if (!self::$installer->isPackageInstalled(self::$localRepo, $package)) {
                    self::$installer->install(self::$localRepo, new InstallOperation($package));
                } else {
                    $notInstalled++;
                }
            }
        }
        if (empty(self::$toInstall) || $notInstalled > 0 ) {
            self::$io->write('Nothing to install or update in platform-specific dependencies');
        }
    }

    public static function update(Event $event)
    {
        //@TODO: update changed packages
        self::install($event);
    }

    /**
     * @param string $packageName
     * @param array $variants
     * @return null|Package
     */
    protected static function createPlatformSpecificPackage($packageName, $variants)
    {
        foreach ($variants as $variant) {
            if (!empty($variant['architecture']) && $variant['architecture'] !== self::getArchitecture())
                continue;

            if (!empty($variant['os']) && $variant['os'] !== self::getOS())
                continue;

            reset($variant);
            $name = key($variant);
            $version = $variant[$name];

            return self::clonePackage($name, $version, $packageName);
        }

        return null;
    }

    /**
     * @param string $name
     * @param string $version
     * @param string $newName
     * @return null|Package
     */
    protected static function clonePackage($name, $version, $newName)
    {
        /* @var PackageInterface $package */
        $package = self::$composer->getRepositoryManager()->findPackage($name, $version);
        if (!$package) {
            return null;
        }
        $cloned = new Package($newName, $package->getVersion(), $package->getPrettyVersion());
        foreach ( self::getPackageClonedMethods() as $method) {
            $cloned->{'set' . $method}($package->{'get' . $method}());
        }
        self::$localRepo->addPackage($cloned);
        return $cloned;
    }

    /**
     * @return string[]
     */
    private static function getPackageClonedMethods()
    {
        $methods = array();
        $ignored = array('Id', 'Repository', 'TargetDir', 'ReleaseDate');
        $reflection = new \ReflectionClass('\Composer\Package\Package');
        foreach ($reflection->getMethods(\ReflectionMethod::IS_PUBLIC) as $method) {
            $name = substr($method->getName(), 3);
            if ( strpos($method->getName(), 'get') === 0 && // is 'get' method
                 $reflection->hasMethod('set'.$name) && // has 'set' method
                 !in_array($name, $ignored) // not in stop list
            ) {
                $methods[] = $name;
            }
        }
        return $methods;
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