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

namespace GsTYPO3\CorePatches\Command\Typo3\Patch;

use Composer\Command\BaseCommand;
use Composer\Composer;
use Composer\Config\JsonConfigSource;
use Composer\Console\Application;
use Composer\DependencyResolver\Operation\UninstallOperation;
use Composer\Factory;
use Composer\Json\JsonFile;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use UnexpectedValueException;

final class RemoveCommand extends BaseCommand
{
    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this
            ->setName('typo3:patch:remove')
            ->setDescription('Remove a TYPO3 core patch.')
            ->setDefinition([
                new InputArgument(
                    'change-id',
                    InputArgument::REQUIRED | InputArgument::IS_ARRAY,
                    'One or multiple change IDs to remove the patch for.'
                ),
            ])
        ;
    }

    /**
     * {@inheritDoc}
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        // Get parameters
        if (!is_array($changeIds = $input->getArgument('change-id'))) {
            throw new UnexpectedValueException('Invalid change IDs.', 1640855652);
        }

        // Get Composer instance
        if (!($composer = $this->getComposer(true)) instanceof Composer) {
            throw new UnexpectedValueException('Invalid Composer instance.', 1640857365);
        }

        $io = $this->getIO();

        $configFile = new JsonFile(Factory::getComposerFile(), null, $io);
        $configSource = new JsonConfigSource($configFile);

        $currentConfig = $configFile->read();
        $extraPatches = [];
        $extraPreferredInstallChanged = [];

        if (
            is_array($currentConfig)
            && is_array($currentConfig['extra'] ?? null)
        ) {
            if (is_array($currentConfig['extra']['patches'] ?? null)) {
                $extraPatches = $currentConfig['extra']['patches'];
            }
            if (
                is_array(
                    $currentConfig['extra']['gilbertsoft/typo3-core-patches']['preferred-install-changed'] ?? null
                )
            ) {
                $extraPreferredInstallChanged =
                    $currentConfig['extra']['gilbertsoft/typo3-core-patches']['preferred-install-changed'];
            }
        }

        $application = new Application();
        $application->setAutoExit(false);

        // Process change IDs
        /** @var array<string, bool> $affectedPackages */
        $affectedPackages = [];
        $patchCount = 0;

        if ($extraPatches !== []) {
            $io->write('<info>Removing patches from composer.json</info>');
            foreach ($changeIds as $changeId) {
                foreach ($extraPatches as $package => &$patches) {
                    foreach ($patches as $subject => $patch) {
                        if (strpos($patch, $changeId . '-') !== false) {
                            $io->write(sprintf('  - Subject is <comment>%s</comment>', $subject));
                            $io->write(sprintf('  - Removing patch <info>%s</info>', $patch));
                            unlink($patch);
                            $affectedPackages[$package] = count($patches) === 1;
                            unset($patches[$subject]);
                            $patchCount += 1;
                        }
                    }
                }
            }

            if ($patchCount > 0) {
                $configSource->removeProperty('extra.patches');
                foreach ($extraPatches as $package => $patches) {
                    if ($patches !== []) {
                        $configSource->addProperty('extra.patches.' . $package, $patches);
                    }
                }
            }
        } else {
            $io->write('<warning>No patches removed</warning>');
        }

        if ($affectedPackages !== []) {
            $repositoryManager = $composer->getRepositoryManager();
            $localRepository = $repositoryManager->getLocalRepository();
            $installationManager = $composer->getInstallationManager();
            $packages = $localRepository->getPackages();
            $promises = [];

            foreach ($packages as $package) {
                $packageName = $package->getName();
                if (isset($affectedPackages[$packageName])) {
                    if (
                        $affectedPackages[$package->getName()]
                        && in_array($packageName, $extraPreferredInstallChanged, true)
                    ) {
                        $io->write(sprintf(
                            '  - Reconfiguring <info>preferred-install</info> for package <info>%s</info>',
                            $packageName
                        ));
                        $configSource->removeConfigSetting(
                            'preferred-install.' . $packageName
                        );
                        if (is_int($key = array_search($packageName, $extraPreferredInstallChanged, true))) {
                            unset($extraPreferredInstallChanged[$key]);
                        }
                    }

                    $uninstallOperation = new UninstallOperation($package);
                    $io->write(sprintf(
                        '<info>Removing package %s so that it can be reinstalled without the removed patch.</info>',
                        $packageName
                    ));
                    $promises[] = $installationManager->uninstall($localRepository, $uninstallOperation);
                }
            }

            $configSource->removeProperty('extra.gilbertsoft/typo3-core-patches.preferred-install-changed');
            if ($extraPreferredInstallChanged !== []) {
                $configSource->addProperty(
                    'extra.gilbertsoft/typo3-core-patches.preferred-install-changed',
                    $extraPreferredInstallChanged
                );
            }

            $promises = array_filter($promises);
            if ($promises !== []) {
                $composer->getLoop()->wait($promises);
            }
        }

        // Update lock file and apply patches
        if (
            $application->run(
                new ArrayInput([
                    'command' => 'update',
                    '--lock' => true,
                ]),
                $output
            ) !== 0
        ) {
            throw new RuntimeException('Error while updating the Composer lock file.', 1641257926);
        }

        $io->write(sprintf(
            '<info>%d TYPO3 core patch%s removed</info>',
            $patchCount,
            $patchCount === 1 ? '' : 'es'
        ));

        return 0;
    }
}
