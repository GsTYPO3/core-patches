<?php

declare(strict_types=1);

/*
 * This file is part of TYPO3 Core Patches.
 *
 * (c) Gilbertsoft LLC (gilbertsoft.org)
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace GsTYPO3\CorePatches\Utility;

use Composer\Composer;
use Composer\Config\JsonConfigSource;
use Composer\Console\Application;
use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\Factory;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Package\PackageInterface;
use GsTYPO3\CorePatches\Exception\CommandExecutionException;
use GsTYPO3\CorePatches\Exception\InvalidResponseException;
use GsTYPO3\CorePatches\Exception\NoPatchException;
use GsTYPO3\CorePatches\Exception\UnexpectedResponseException;
use GsTYPO3\CorePatches\Exception\UnexpectedValueException;
use React\Promise\PromiseInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;

final class ComposerUtils
{
    private const EXTRA_BASE = 'gilbertsoft/typo3-core-patches';
    private const EXTRA_APPLIED_CHANGES = 'applied-changes';
    private const EXTRA_PREFERRED_INSTALL_CHANGED = 'preferred-install-changed';

    /** @var Composer */
    private $composer;
    /** @var IOInterface */
    private $io;
    /** @var Application */
    private $application;
    /** @var JsonFile */
    private $configFile;
    /** @var JsonConfigSource */
    private $configSource;

    public function __construct(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;

        $this->configFile = new JsonFile(Factory::getComposerFile(), null, $this->io);
        $this->configSource = new JsonConfigSource($this->configFile);
        $this->application = new Application();
        $this->application->setAutoExit(false);
    }

    /**
     * @return array<string, string>
     */
    private function getPreferredInstall(): array
    {
        $config = $this->configFile->read();

        if (
            !is_array($config)
            || !is_array($config['config'] ?? null)
            || !is_array($config['config']['preferred-install'] ?? null)
        ) {
            return [];
        }

        return $config['config']['preferred-install'];
    }

    /**
     * @return array<int, int>
     */
    private function getAppliedChanges(): array
    {
        $config = $this->configFile->read();

        if (
            !is_array($config)
            || !is_array($config['extra'] ?? null)
            || !is_array($config['extra'][self::EXTRA_BASE] ?? null)
            || !is_array($config['extra'][self::EXTRA_BASE][self::EXTRA_APPLIED_CHANGES] ?? null)
        ) {
            return [];
        }

        return $config['extra'][self::EXTRA_BASE][self::EXTRA_APPLIED_CHANGES];
    }

    /**
     * @return array<int, string>
     */
    private function getPreferredInstallChanged(): array
    {
        $config = $this->configFile->read();

        if (
            !is_array($config)
            || !is_array($config['extra'] ?? null)
            || !is_array($config['extra'][self::EXTRA_BASE] ?? null)
            || !is_array($config['extra'][self::EXTRA_BASE][self::EXTRA_PREFERRED_INSTALL_CHANGED] ?? null)
        ) {
            return [];
        }

        return $config['extra'][self::EXTRA_BASE][self::EXTRA_PREFERRED_INSTALL_CHANGED];
    }

    /**
     * @return array<string, array<string, string>>
     */
    private function getPatches(): array
    {
        $config = $this->configFile->read();

        if (
            !is_array($config)
            || !is_array($config['extra'] ?? null)
            || !is_array($config['extra']['patches'] ?? null)
        ) {
            return [];
        }

        return $config['extra']['patches'];
    }

    /**
     * @param array<string, array<string, string>> $patches
     */
    private function addPatchesToConfigFile(array $patches): void
    {
        foreach ($patches as $packageName => $packagePatches) {
            $this->configSource->addProperty(sprintf('extra.patches.%s', $packageName), $packagePatches);
        }
    }

    /**
     * @param array<string, array<string, string>> $patches
     */
    private function removePatchesFromConfigFile(array $patches): void
    {
        foreach ($patches as $packageName => $packagePatches) {
            $this->configSource->removeProperty(sprintf('extra.patches.%s', $packageName));
        }
    }

    private function setSourceInstall(string $packageName): void
    {
        $currentValue = $this->getPreferredInstall();

        if (isset($currentValue[$packageName]) && $currentValue[$packageName] === 'source') {
            // source install for this package is already configured, skip it
            return;
        }

        $this->configSource->addConfigSetting(
            'preferred-install.' . $packageName,
            'source'
        );

        $this->addPreferredInstallChanged($packageName);
    }

    private function unsetSourceInstall(string $packageName): void
    {
        $currentValue = $this->getPreferredInstallChanged();

        if (!in_array($packageName, $currentValue, true)) {
            // source was not set by this package, skip removal
            return;
        }

        $this->configSource->removeConfigSetting(
            'preferred-install.' . $packageName
        );

        $this->removePreferredInstallChanged($packageName);
    }

    private function addAppliedChange(int $numericId): void
    {
        $currentValue = $this->getAppliedChanges();

        if (in_array($numericId, $currentValue, true)) {
            // the package is already listed, skip addition
            return;
        }

        $currentValue[] = $numericId;
        sort($currentValue);

        $this->configSource->addProperty(
            sprintf(
                'extra.%s.%s',
                self::EXTRA_BASE,
                self::EXTRA_APPLIED_CHANGES
            ),
            $currentValue
        );
    }

    private function removeAppliedChange(int $numericId): void
    {
        $currentValue = $this->getAppliedChanges();

        if (!in_array($numericId, $currentValue, true)) {
            // the package is not listed, skip removal
            return;
        }

        $currentValue = array_filter($currentValue, function ($value) use ($numericId) {
            return $value !== $numericId;
        });
        sort($currentValue);

        $this->configSource->addProperty(
            sprintf(
                'extra.%s.%s',
                self::EXTRA_BASE,
                self::EXTRA_APPLIED_CHANGES
            ),
            $currentValue
        );
    }

    private function addPreferredInstallChanged(string $packageName): void
    {
        $currentValue = $this->getPreferredInstallChanged();

        if (in_array($packageName, $currentValue, true)) {
            // the package is already listed, skip addition
            return;
        }

        $currentValue[] = $packageName;
        sort($currentValue);

        $this->configSource->addProperty(
            sprintf(
                'extra.%s.%s',
                self::EXTRA_BASE,
                self::EXTRA_PREFERRED_INSTALL_CHANGED
            ),
            $currentValue
        );
    }

    private function removePreferredInstallChanged(string $packageName): void
    {
        $currentValue = $this->getPreferredInstallChanged();

        if (!in_array($packageName, $currentValue, true)) {
            // the package is not listed, skip removal
            return;
        }

        $currentValue = array_filter($currentValue, function ($value) use ($packageName) {
            return $value !== $packageName;
        });
        sort($currentValue);

        $this->configSource->addProperty(
            sprintf(
                'extra.%s.%s',
                self::EXTRA_BASE,
                self::EXTRA_PREFERRED_INSTALL_CHANGED
            ),
            $currentValue
        );
    }

    /**
     * @throws CommandExecutionException
     */
    private function checkCommandResult(int $resultCode, int $expectedCode, string $errorMessage): void
    {
        if ($resultCode !== $expectedCode) {
            throw new CommandExecutionException($errorMessage, $resultCode);
        }
    }

    /**
     * @param array<int, string>   $changeIds
     * @return int The number of patches added
     */
    public function addPatches(array $changeIds, string $destination, bool $includeTests): int
    {
        $gerritUtils = new GerritUtils(Factory::createHttpDownloader($this->io, $this->composer->getConfig()));
        $patchUtils = new PatchUtils($this->composer, $this->io);

        // Process change IDs
        $patches = [];
        $patchesCount = 0;
        $affectedPackages = [];

        foreach ($changeIds as $changeId) {
            $this->io->write(sprintf('<info>Creating patches for change <comment>%s</comment></info>', $changeId));

            try {
                $subject = $gerritUtils->getSubject($changeId);
                $this->io->write(sprintf('  - Subject is <comment>%s</comment>', $subject));

                $numericId = $gerritUtils->getNumericId($changeId);
                $this->io->write(sprintf('  - Numeric ID is <comment>%s</comment>', $numericId));
            } catch (UnexpectedResponseException | InvalidResponseException | UnexpectedValueException $th) {
                $this->io->writeError('<warning>Error getting change from Gerrit</warning>');
                $this->io->writeError(sprintf(
                    '<warning>%s</warning>',
                    $th->getMessage()
                ), true, IOInterface::VERBOSE);

                continue;
            }

            try {
                $patches = $patchUtils->create(
                    $numericId,
                    $subject,
                    $gerritUtils->getPatch($changeId),
                    $destination,
                    $includeTests
                );
            } catch (NoPatchException $th) {
                $this->io->writeError('<warning>No patches saved for this change</warning>');

                continue;
            }

            $patchesCreated = count($patches);

            $this->io->write(sprintf(
                '  - Change saved to <comment>%d</comment> patch%s',
                $patchesCreated,
                $patchesCreated === 1 ? '' : 'es'
            ));

            // Adding patches to configuration
            $this->io->write('  - Adding patches to <info>composer.json</info>');
            $this->addPatchesToConfigFile($patches);
            $this->addAppliedChange($numericId);

            $affectedPackages = array_merge($affectedPackages, array_keys($patches));

            $patchesCount += $patchesCreated;
        }

        if ($patchesCount > 0) {
            // Reconfigure preferred-install for packages if changes of tests are patched too
            if ($includeTests) {
                $this->io->write(
                    '<info>Reconfiguring <comment>preferred-install</comment> to <comment>source</comment></info>'
                );

                $affectedPackages = array_unique($affectedPackages);
                $packages = $this->composer->getRepositoryManager()->getLocalRepository()->getPackages();
                $promises = [];

                foreach ($packages as $package) {
                    $packageName = $package->getName();
                    if (in_array($packageName, $affectedPackages, true)) {
                        $this->io->write(sprintf(
                            '  - <info>%s</info>',
                            $packageName
                        ));

                        $this->setSourceInstall($packageName);

                        // Uninstall package to force reinstall from sources
                        $promises[] = $this->uninstallPackage($package);

                        // Remove package from the array
                        $affectedPackages = array_filter($affectedPackages, function ($value) use ($packageName) {
                            return $value !== $packageName;
                        });
                    }
                }

                // Let Composer remove the packages marked for uninstall
                $promises = array_filter($promises);
                if ($promises !== []) {
                    $this->composer->getLoop()->wait($promises);
                }

                // Show a warning for patches of missing packages
                if ($affectedPackages !== []) {
                    $this->io->write('<warning>Patches for non-existent packages found, these are:</warning>');
                    foreach ($affectedPackages as $packageName) {
                        $this->io->write(sprintf(
                            '  - <info>%s</info>',
                            $packageName
                        ));
                    }
                }
            }
        } else {
            $this->io->write('<warning>No patches created</warning>');
        }

        return $patchesCount;
    }

    /**
     * @param array<int, string>   $changeIds
     * @return int The number of patches removed
     */
    public function removePatches(array $changeIds): int
    {
        $gerritUtils = new GerritUtils(Factory::createHttpDownloader($this->io, $this->composer->getConfig()));
        $patchUtils = new PatchUtils($this->composer, $this->io);

        // Process change IDs
        $appliedChanges = $this->getAppliedChanges();
        $numericIds = [];

        foreach ($changeIds as $changeId) {
            $this->io->write(sprintf(
                '<info>Collecting information for change <comment>%s</comment></info>',
                $changeId
            ));

            try {
                $numericId = $gerritUtils->getNumericId($changeId);
                $this->io->write(sprintf('  - Numeric ID is <comment>%s</comment>', $numericId));
            } catch (UnexpectedResponseException | InvalidResponseException | UnexpectedValueException $th) {
                $this->io->writeError('<warning>Error getting numeric ID</warning>');
                $this->io->writeError(sprintf(
                    '<warning>%s</warning>',
                    $th->getMessage()
                ), true, IOInterface::VERBOSE);

                continue;
            }

            // Check if change was previously added by this plugin or skip
            if (!in_array($numericId, $appliedChanges, true)) {
                $this->io->writeError('<warning>Change was not applied by this plugin, skipping</warning>');

                continue;
            }

            $numericIds[] = $numericId;
        }

        // Remove patches
        $patchesCount = 0;
        $patches = $this->getPatches();

        if ($patches !== [] && $numericIds !== []) {
            /** @var array<string, bool> $affectedPackages */
            $affectedPackages = [];

            $this->io->write('<info>Removing patches</info>');

            $patchesToRemove = $patchUtils->remove($numericIds, $patches);

            $this->io->write('  - Removing patches from <info>composer.json</info>');
            $this->removePatchesFromConfigFile($patchesToRemove);

            if ($patchesToRemove !== []) {
                // Revert source install if the last patch was removed
                $patches = $this->getPatches();

                foreach ($patchesToRemove as $packageName => $packagePatches) {
                    if (!isset($patches[$packageName])) {
                        $this->io->write(sprintf(
                            '  - Reconfiguring <info>preferred-install</info> for package <info>%s</info>',
                            $packageName
                        ));
                        $this->unsetSourceInstall($packageName);
                    }

                    $patchesCount += count($packagePatches);
                }

                // Uninstall packages with removed patches
                $packages = $this->composer->getRepositoryManager()->getLocalRepository()->getPackages();
                $promises = [];

                foreach ($packages as $package) {
                    $packageName = $package->getName();
                    if (isset($patchesToRemove[$packageName])) {
                        $this->io->write(sprintf(
                            '<info>Removing package %s so that it can be reinstalled without the removed patch.</info>',
                            $packageName
                        ));
                        $promises[] = $this->uninstallPackage($package);
                    }
                }

                // Let Composer remove the packages marked for uninstall
                $promises = array_filter($promises);
                if ($promises !== []) {
                    $this->composer->getLoop()->wait($promises);
                }
            }

            // Remove patches from applied changes
            foreach ($numericIds as $numericId) {
                $this->removeAppliedChange($numericId);
            }
        } else {
            $this->io->write('<warning>No patches removed</warning>');
        }

        return $patchesCount;
    }

    /**
     * @return PromiseInterface|null
     */
    public function uninstallPackage(PackageInterface $package): ?PromiseInterface
    {
        return $this->composer->getInstallationManager()->uninstall(
            $this->composer->getRepositoryManager()->getLocalRepository(),
            new UninstallOperation($package)
        );
    }

    /**
     * @return void
     * @throws CommandExecutionException
     */
    public function updateLock(OutputInterface $output): void
    {
        $this->checkCommandResult(
            $this->application->run(
                new ArrayInput([
                    'command' => 'update',
                    '--lock' => true,
                ]),
                $output
            ),
            0,
            'Error while updating the Composer lock file.'
        );
    }
}
