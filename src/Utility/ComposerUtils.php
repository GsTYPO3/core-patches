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
use GsTYPO3\CorePatches\Gerrit\RestApi;
use React\Promise\PromiseInterface;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\OutputInterface;

final class ComposerUtils
{
    /**
     * @var string
     */
    private const CONFIG = 'config';

    /**
     * @var string
     */
    private const CONFIG_PREFERRED_INSTALL = 'preferred-install';

    /**
     * @var string
     */
    private const EXTRA = 'extra';

    /**
     * @var string
     */
    private const EXTRA_BASE = 'gilbertsoft/typo3-core-patches';

    /**
     * @var string
     */
    private const EXTRA_APPLIED_CHANGES = 'applied-changes';

    /**
     * @var string
     */
    private const EXTRA_PREFERRED_INSTALL_CHANGED = 'preferred-install-changed';

    private Composer $composer;

    private IOInterface $io;

    private Application $application;

    private JsonFile $configFile;

    private JsonConfigSource $configSource;

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
     * @return array<int, string>
     */
    private function getPreferredInstallChanged(): array
    {
        $config = $this->configFile->read();

        if (
            !is_array($config)
            || !is_array($config[self::EXTRA] ?? null)
            || !is_array($config[self::EXTRA][self::EXTRA_BASE] ?? null)
            || !is_array($config[self::EXTRA][self::EXTRA_BASE][self::EXTRA_PREFERRED_INSTALL_CHANGED] ?? null)
        ) {
            return [];
        }

        return $config[self::EXTRA][self::EXTRA_BASE][self::EXTRA_PREFERRED_INSTALL_CHANGED];
    }

    /**
     * @return array<int, int>
     */
    private function getAppliedChanges(): array
    {
        $config = $this->configFile->read();

        if (
            !is_array($config)
            || !is_array($config[self::EXTRA] ?? null)
            || !is_array($config[self::EXTRA][self::EXTRA_BASE] ?? null)
            || !is_array($config[self::EXTRA][self::EXTRA_BASE][self::EXTRA_APPLIED_CHANGES] ?? null)
        ) {
            return [];
        }

        return $config[self::EXTRA][self::EXTRA_BASE][self::EXTRA_APPLIED_CHANGES];
    }

    /**
     * @return array<string, string>
     */
    private function getPreferredInstall(): array
    {
        $config = $this->configFile->read();

        if (
            !is_array($config)
            || !is_array($config[self::CONFIG] ?? null)
            || !is_array($config[self::CONFIG][self::CONFIG_PREFERRED_INSTALL] ?? null)
        ) {
            return [];
        }

        return $config[self::CONFIG][self::CONFIG_PREFERRED_INSTALL];
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

        $currentValue = array_filter($currentValue, fn ($value): bool => $value !== $packageName);
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
     * @param array<string, array<string, string>> $patches
     */
    private function addPatchesToConfigFile(array $patches): void
    {
        foreach ($patches as $packageName => $packagePatches) {
            $this->configSource->addProperty(sprintf('extra.patches.%s', $packageName), $packagePatches);
        }
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

    /**
     * @return array<string, array<string, string>>
     */
    private function getPatches(): array
    {
        $config = $this->configFile->read();

        if (
            !is_array($config)
            || !is_array($config[self::EXTRA] ?? null)
            || !is_array($config[self::EXTRA]['patches'] ?? null)
        ) {
            return [];
        }

        return $config[self::EXTRA]['patches'];
    }

    /**
     * @param array<string, array<string, string>> $patches
     */
    private function removePatchesFromConfigFile(array $patches): void
    {
        foreach (array_keys($patches) as $packageName) {
            $this->configSource->removeProperty(sprintf('extra.patches.%s', $packageName));
        }
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

    private function removeAppliedChange(int $numericId): void
    {
        $currentValue = $this->getAppliedChanges();

        if (!in_array($numericId, $currentValue, true)) {
            // the package is not listed, skip removal
            return;
        }

        $currentValue = array_filter($currentValue, fn ($value): bool => $value !== $numericId);
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
        $gerritRestApi = new RestApi(Factory::createHttpDownloader($this->io, $this->composer->getConfig()));
        $patchUtils = new PatchUtils($this->composer, $this->io);

        // Process change IDs
        $patches = [];
        $patchesCount = 0;
        $affectedPackages = [];

        foreach ($changeIds as $changeId) {
            $this->io->write(sprintf('<info>Creating patches for change <comment>%s</comment></info>', $changeId));

            try {
                $subject = $gerritRestApi->getSubject($changeId);
                $this->io->write(sprintf('  - Subject is <comment>%s</comment>', $subject));

                $numericId = $gerritRestApi->getNumericId($changeId);
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
                    $gerritRestApi->getPatch($changeId),
                    $destination,
                    $includeTests
                );
            } catch (NoPatchException $noPatchException) {
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

            $affectedPackages = [...$affectedPackages, ...array_keys($patches)];

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
                        $affectedPackages = array_filter(
                            $affectedPackages,
                            fn ($value): bool => $value !== $packageName
                        );
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
                    foreach ($affectedPackages as $affectedPackage) {
                        $this->io->write(sprintf(
                            '  - <info>%s</info>',
                            $affectedPackage
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
        $gerritRestApi = new RestApi(Factory::createHttpDownloader($this->io, $this->composer->getConfig()));
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
                $numericId = $gerritRestApi->getNumericId($changeId);
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

    public function uninstallPackage(PackageInterface $package): ?PromiseInterface
    {
        return $this->composer->getInstallationManager()->uninstall(
            $this->composer->getRepositoryManager()->getLocalRepository(),
            new UninstallOperation($package)
        );
    }

    /**
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
