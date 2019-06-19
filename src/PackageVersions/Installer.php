<?php

declare(strict_types=1);

namespace PackageVersions;

use Composer\Composer;
use Composer\Config;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\IO\IOInterface;
use Composer\Package\AliasPackage;
use Composer\Package\Locker;
use Composer\Package\PackageInterface;
use Composer\Package\RootPackageInterface;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use Generator;
use RuntimeException;
use function array_key_exists;
use function array_merge;
use function chmod;
use function dirname;
use function file_exists;
use function file_put_contents;
use function iterator_to_array;
use function rename;
use function sprintf;
use function uniqid;
use function var_export;

final class Installer implements PluginInterface, EventSubscriberInterface
{
    /** @var string */
    private static $generatedClassTemplate = <<<'PHP'
<?php

declare(strict_types=1);

namespace PackageVersions;

/**
 * This class is generated by ocramius/package-versions, specifically by
 * @see \PackageVersions\Installer
 *
 * This file is overwritten at every run of `composer install` or `composer update`.
 */
%s
{
    const ROOT_PACKAGE_NAME = '%s';
    /**
     * Array of all available composer packages.
     * Dont read this array from your calling code, but use the \PackageVersions\Versions::getVersion() method instead.
     *
     * @var array<string, string>
     * @internal
     */
    const VERSIONS          = %s;

    private function __construct()
    {
    }

    /**
     * @throws \OutOfBoundsException If a version cannot be located.
     *
     * @psalm-param key-of<self::VERSIONS> $packageName
     */
    public static function getVersion(string $packageName) : string
    {
        if (isset(self::VERSIONS[$packageName])) {
            return self::VERSIONS[$packageName];
        }

        throw new \OutOfBoundsException(
            'Required package "' . $packageName . '" is not installed: cannot detect its version'
        );
    }
}

PHP;

    /**
     * {@inheritDoc}
     */
    public function activate(Composer $composer, IOInterface $io) 
    {
        // Nothing to do here, as all features are provided through event listeners
    }

    /**
     * {@inheritDoc}
     */
    public static function getSubscribedEvents() : array
    {
        return [
            ScriptEvents::POST_INSTALL_CMD => 'dumpVersionsClass',
            ScriptEvents::POST_UPDATE_CMD  => 'dumpVersionsClass',
            ScriptEvents::POST_AUTOLOAD_DUMP  => 'dumpVersionsClass',
        ];
    }

    /**
     * @throws RuntimeException
     */
    public static function dumpVersionsClass(Event $composerEvent)
    {
        $composer    = $composerEvent->getComposer();
        $rootPackage = $composer->getPackage();
        $versions    = iterator_to_array(self::getVersions($composer->getLocker(), $rootPackage));

        if (! array_key_exists('ocramius/package-versions', $versions)) {
            //plugin must be globally installed - we only want to generate versions for projects which specifically
            //require ocramius/package-versions
            return;
        }

        $versionClass = self::generateVersionsClass($rootPackage->getName(), $versions);

        self::writeVersionClassToFile($versionClass, $composer, $composerEvent->getIO());
    }

    /**
     * @param string[] $versions
     */
    private static function generateVersionsClass(string $rootPackageName, array $versions) : string
    {
        return sprintf(
            self::$generatedClassTemplate,
            'fin' . 'al ' . 'cla' . 'ss ' . 'Versions', // note: workaround for regex-based code parsers :-(
            $rootPackageName,
            var_export($versions, true)
        );
    }

    /**
     * @throws RuntimeException
     */
    private static function writeVersionClassToFile(string $versionClassSource, Composer $composer, IOInterface $io)
    {
        $installPath = self::locateRootPackageInstallPath($composer->getConfig(), $composer->getPackage())
            . '/src/PackageVersions/Versions.php';

        if (! file_exists(dirname($installPath))) {
            $io->write('<info>ocramius/package-versions:</info> Package not found (probably scheduled for removal); generation of version class skipped.');

            return;
        }

        $io->write('<info>ocramius/package-versions:</info> Generating version class...');

        $installPathTmp = $installPath . '_' . uniqid('tmp', true);
        file_put_contents($installPathTmp, $versionClassSource);
        chmod($installPathTmp, 0664);
        rename($installPathTmp, $installPath);

        $io->write('<info>ocramius/package-versions:</info> ...done generating version class');
    }

    /**
     * @throws RuntimeException
     */
    private static function locateRootPackageInstallPath(
        Config $composerConfig,
        RootPackageInterface $rootPackage
    ) : string {
        if (self::getRootPackageAlias($rootPackage)->getName() === 'ocramius/package-versions') {
            return dirname($composerConfig->get('vendor-dir'));
        }

        return $composerConfig->get('vendor-dir') . '/ocramius/package-versions';
    }

    private static function getRootPackageAlias(RootPackageInterface $rootPackage) : PackageInterface
    {
        $package = $rootPackage;

        while ($package instanceof AliasPackage) {
            $package = $package->getAliasOf();
        }

        return $package;
    }

    /**
     * @return Generator|string[]
     */
    private static function getVersions(Locker $locker, RootPackageInterface $rootPackage) : Generator
    {
        $lockData = $locker->getLockData();

        $lockData['packages-dev'] = $lockData['packages-dev'] ?? [];

        foreach (array_merge($lockData['packages'], $lockData['packages-dev']) as $package) {
            yield $package['name'] => $package['version'] . '@' . (
                $package['source']['reference']?? $package['dist']['reference'] ?? ''
            );
        }

        foreach ($rootPackage->getReplaces() as $replace) {
            $version = $replace->getPrettyConstraint();
            if ($version === 'self.version') {
                $version = $rootPackage->getPrettyVersion();
            }

            yield $replace->getTarget() => $version . '@' . $rootPackage->getSourceReference();
        }

        yield $rootPackage->getName() => $rootPackage->getPrettyVersion() . '@' . $rootPackage->getSourceReference();
    }
}
