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
use Composer\Console\Application;
use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\Factory;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Package\BasePackage;
use Composer\Package\PackageInterface;
use Composer\Semver\Semver;
use Composer\Semver\VersionParser;
use GsTYPO3\CorePatches\Config;
use GsTYPO3\CorePatches\Config\Patches;
use GsTYPO3\CorePatches\Config\PreferredInstall;
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
    private Composer $composer;

    private IOInterface $io;

    private Config $config;

    private Application $application;

    private RestApi $gerritRestApi;

    private PatchUtils $patchUtils;

    private VersionParser $versionParser;

    public function __construct(
        Composer $composer,
        IOInterface $io,
        ?Config $config = null,
        ?Application $application = null,
        ?RestApi $restApi = null,
        ?PatchUtils $patchUtils = null,
        ?VersionParser $versionParser = null
    ) {
        $this->composer = $composer;
        $this->io = $io;

        $httpDownloader = Factory::createHttpDownloader($this->io, $this->composer->getConfig());

        $this->config = $config ?? new Config(
            new JsonFile(Factory::getComposerFile(), $httpDownloader, $this->io),
            $this->composer->getConfig()->getConfigSource()
        );

        $this->application = $application ?? new Application();
        $this->application->setAutoExit(false);

        $this->gerritRestApi = $restApi ?? new RestApi($httpDownloader);
        $this->patchUtils = $patchUtils ?? new PatchUtils($this->composer, $this->io);
        $this->versionParser = $versionParser ?? new VersionParser();
    }

    /**
     * @param array<string, array<string, string>> $patches
     */
    private function addPatchesToConfigFile(array $patches): void
    {
        $configPatches = $this->config->load()->getPatches();

        foreach ($patches as $packgeName => $packagePatches) {
            $configPatches->add($packgeName, $packagePatches);
        }

        $this->config->save();
    }

    /**
     * @param array<int, string> $packages
     */
    private function addAppliedChange(
        int $numericId,
        array $packages,
        bool $includeTests = false,
        string $destination = '',
        int $revision = -1
    ): void {
        $changes = $this->config->load()->getChanges();

        if ($changes->has($numericId)) {
            // the package is already listed, skip addition
            return;
        }

        // Avoid saving the patch directory to the patches if default path is used.
        if ($destination === $this->config->getPatchDirectory()) {
            $destination = '';
        }

        $changes->add($numericId, $packages, $includeTests, $destination, $revision);

        $this->config->save();
    }

    private function addPreferredInstallChanged(string $packageName): void
    {
        $preferredInstallChanged = $this->config->load()->getPreferredInstallChanged();

        if ($preferredInstallChanged->has($packageName)) {
            // the package is already listed, skip addition
            return;
        }

        $preferredInstallChanged->add($packageName);
        $this->config->save();
    }

    private function removePreferredInstallChanged(string $packageName): void
    {
        $preferredInstallChanged = $this->config->load()->getPreferredInstallChanged();

        if (!$preferredInstallChanged->has($packageName)) {
            // the package is not listed, skip removal
            return;
        }

        $preferredInstallChanged->remove($packageName);
        $this->config->save();
    }

    /**
     * @param array<int, string> $changeIds
     * @param array<int, string> $affectedPackages
     */
    private function createPatches(
        array $changeIds,
        string $destination,
        bool $includeTests,
        array &$affectedPackages
    ): int {
        // Process change IDs
        $patchesCount = 0;

        foreach ($changeIds as $changeId) {
            $this->io->write(sprintf('<info>Creating patches for change <comment>%s</comment></info>', $changeId));

            try {
                $subject = $this->gerritRestApi->getSubject($changeId);
                $this->io->write(sprintf('  - Subject is <comment>%s</comment>', $subject));

                $numericId = $this->gerritRestApi->getNumericId($changeId);
                $this->io->write(sprintf('  - Numeric ID is <comment>%s</comment>', $numericId));

                $branch = $this->gerritRestApi->getBranch($changeId);
                $this->io->write(sprintf('  - Branch is <comment>%s</comment>', $branch));
            } catch (UnexpectedResponseException | InvalidResponseException | UnexpectedValueException $th) {
                $this->io->writeError('<warning>Error getting change from Gerrit</warning>');
                $this->io->writeError(sprintf(
                    '<warning>%s</warning>',
                    $th->getMessage()
                ), true, IOInterface::VERBOSE);

                continue;
            }

            if (!$this->checkBranch($numericId, $branch)) {
                $this->io->writeError(sprintf(
                    '<warning>Skipping change %s not matching the installed branch</warning>',
                    $numericId
                ));

                continue;
            }

            try {
                $patches = $this->patchUtils->create(
                    $numericId,
                    $subject,
                    $this->gerritRestApi->getPatch($changeId),
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
            $this->addAppliedChange($numericId, array_keys($patches), $includeTests, $destination);

            $affectedPackages = [...$affectedPackages, ...array_keys($patches)];

            $patchesCount += $patchesCreated;
        }

        return $patchesCount;
    }

    private function checkBranch(int $changeId, string $targetBranch): bool
    {
        if ($this->config->load()->getIgnoreBranch()) {
            return true;
        }

        if (
            !$this->composer->getRepositoryManager()->getLocalRepository()->findPackage(
                'typo3/cms-core',
                $this->versionParser->normalizeBranch($targetBranch)
            ) instanceof BasePackage
        ) {
            return $this->io->askConfirmation(
                sprintf(
                    '<info>The change %d is related to the branch "%s" but another version appears to be installed.' .
                    ' Applying the patch may result in errors afterwards. Should the patch for this change be' .
                    ' installed anyway?</info> [<comment>y,N</comment>] ',
                    $changeId,
                    $targetBranch
                ),
                false
            );
        }

        return true;
    }

    private function setSourceInstall(string $packageName): void
    {
        /*
        $currentValue = $this->getPreferredInstall();

        if (isset($currentValue[$packageName]) && $currentValue[$packageName] === 'source') {
            // source install for this package is already configured, skip it
            return;
        }

        $this->configSource->addConfigSetting(
            'preferred-install.' . $packageName,
            'source'
        );
        */

        $preferredInstall = $this->config->load()->getPreferredInstall();

        if ($preferredInstall->has($packageName, PreferredInstall::METHOD_SOURCE)) {
            // source install for this package is already configured, skip it
            return;
        }

        $preferredInstall->add($packageName, PreferredInstall::METHOD_SOURCE);

        $this->config->save();

        $this->addPreferredInstallChanged($packageName);
    }

    private function uninstallPackage(PackageInterface $package): ?PromiseInterface
    {
        return $this->composer->getInstallationManager()->uninstall(
            $this->composer->getRepositoryManager()->getLocalRepository(),
            new UninstallOperation($package)
        );
    }

    private function removePatchesFromConfigFile(Patches $patches): void
    {
        $configPatches = $this->config->load()->getPatches();

        foreach ($patches as $packageName => $packagePatches) {
            $configPatches->remove($packageName, $packagePatches);
        }

        $this->config->save();
    }

    private function unsetSourceInstall(string $packageName): void
    {
        $config = $this->config->load();
        $preferredInstallChanged = $config->getPreferredInstallChanged();

        if (!$preferredInstallChanged->has($packageName)) {
            // source was not set by this package, skip removal
            return;
        }

        $preferredInstall = $config->getPreferredInstall();
        $preferredInstall->remove($packageName);

        $this->config->save();

        $this->removePreferredInstallChanged($packageName);
    }

    private function removeAppliedChange(int $numericId): void
    {
        $changes = $this->config->load()->getChanges();

        if (!$changes->has($numericId)) {
            // the package is not listed, skip removal
            return;
        }

        $changes->remove($numericId);

        $this->config->save();
    }

    /**
     * @return array<int, string>
     */
    private function getPackagesForChange(int $changeId): array
    {
        $changePackages = [];

        $patches = $this->config->getPatches();

        foreach ($patches as $packageName => $packagePatches) {
            foreach ($packagePatches as $packagePatch) {
                if ($this->patchUtils->patchIsPartOfChange($packagePatch, $changeId)) {
                    $changePackages[] = $packageName;
                }
            }
        }

        return $changePackages;
    }

    private function askRemoval(int $changeId): bool
    {
        return $this->io->askConfirmation(
            sprintf(
                '<info>The change %d appears to be present in the version being installed or updated.' .
                ' Should the patch for this change be removed?</info> [<comment>Y,n</comment>] ',
                $changeId
            ),
            $this->config->load()->getForceTidyPatches()
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
     * @param  array<int, string> $changeIds
     * @return int                The number of patches added
     */
    public function addPatches(array $changeIds, string $destination, bool $includeTests): int
    {
        $affectedPackages = [];
        $patchesCount = $this->createPatches($changeIds, $destination, $includeTests, $affectedPackages);

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
                            static fn ($value): bool => $value !== $packageName
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
     * @param  array<int, string> $changeIds
     * @return int                The number of patches removed
     */
    public function removePatches(array $changeIds, bool $skipUninstall = false): int
    {
        // Process change IDs
        $appliedChanges = $this->config->load()->getChanges();
        $numericIds = [];

        foreach ($changeIds as $changeId) {
            $this->io->write(sprintf(
                '<info>Collecting information for change <comment>%s</comment></info>',
                $changeId
            ));

            try {
                $numericId = $this->gerritRestApi->getNumericId($changeId);
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
            if (!$appliedChanges->has($numericId)) {
                $this->io->writeError('<warning>Change was not applied by this plugin, skipping</warning>');

                continue;
            }

            $numericIds[] = $numericId;
        }

        // Remove patches
        $patchesCount = 0;
        $patches = $this->config->getPatches();

        if (!$patches->isEmpty() && $numericIds !== []) {
            $this->io->write('<info>Removing patches</info>');

            $patchesToRemove = $this->patchUtils->remove($numericIds, $patches);

            $this->io->write('  - Removing patches from <info>composer.json</info>');
            $this->removePatchesFromConfigFile($patchesToRemove);

            if (!$patchesToRemove->isEmpty()) {
                // Revert source install if the last patch was removed
                $patches = $this->config->getPatches();

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

                if (!$skipUninstall) {
                    // Uninstall packages with removed patches
                    $packages = $this->composer->getRepositoryManager()->getLocalRepository()->getPackages();
                    $promises = [];

                    foreach ($packages as $package) {
                        $packageName = $package->getName();
                        if (isset($patchesToRemove[$packageName])) {
                            $this->io->write(sprintf(
                                '<info>Removing package %s so that it can be reinstalled without the patch.</info>',
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
     * @param  array<int, string> $changeIds
     * @return int                The number of patches updated
     */
    public function updatePatches(array $changeIds): int
    {
        $patchesCount = 0;
        $affectedPackages = [];

        $appliedChanges = $this->config->load()->getChanges();
        $patchDirectory = $this->config->getPatchDirectory();

        foreach ($appliedChanges as $appliedChange) {
            if ($changeIds === [] || in_array((string)$appliedChange->getNumber(), $changeIds, true)) {
                $patchesCount += $this->createPatches(
                    [(string)$appliedChange->getNumber()],
                    $appliedChange->getPatchDirectory() !== ''
                        ? $appliedChange->getPatchDirectory() : $patchDirectory,
                    $appliedChange->getTests(),
                    $affectedPackages
                );
            }
        }

        return $patchesCount;
    }

    /**
     * @return array<int, string>
     */
    public function verifyPatchesForPackage(PackageInterface $package): array
    {
        $obsoleteChanges = [];
        $appliedChanges = $this->config->load()->getChanges();
        $patches = $this->config->getPatches();

        foreach ($appliedChanges as $appliedChange) {
            $packages = $this->getPackagesForChange($appliedChange->getNumber());

            if (in_array($package->getName(), $packages, true)) {
                $includedInInfo = $this->gerritRestApi->getIncludedIn((string)$appliedChange->getNumber());

                foreach ($includedInInfo->tags as $tag) {
                    if (Semver::satisfies($package->getVersion(), $tag)) {
                        if ($this->askRemoval($appliedChange->getNumber())) {
                            $obsoleteChanges[] = (string)$appliedChange->getNumber();
                            $this->patchUtils->prepareRemove([$appliedChange->getNumber()], $patches);
                        }

                        continue 2;
                    }
                }

                foreach ($includedInInfo->branches as $branch) {
                    if (Semver::satisfies($package->getVersion(), 'dev-' . $branch)) {
                        if ($this->askRemoval($appliedChange->getNumber())) {
                            $obsoleteChanges[] = (string)$appliedChange->getNumber();
                            $this->patchUtils->prepareRemove([$appliedChange->getNumber()], $patches);
                        }

                        continue 2;
                    }
                }
            }
        }

        return $obsoleteChanges;
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
