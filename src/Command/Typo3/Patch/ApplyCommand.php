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
use Composer\Factory;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use GsTYPO3\CorePatches\Utility\Gerrit;
use GsTYPO3\CorePatches\Utility\PatchCreator;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use UnexpectedValueException;

final class ApplyCommand extends BaseCommand
{
    /**
     * {@inheritDoc}
     */
    protected function configure()
    {
        $this
            ->setName('typo3:patch:apply')
            ->setDescription('Add and apply a TYPO3 core patch.')
            ->setDefinition([
                new InputArgument(
                    'change-id',
                    InputArgument::REQUIRED | InputArgument::IS_ARRAY,
                    'One or multiple change IDs to add a patch for.'
                ),
                new InputOption(
                    'patch-dir',
                    'p',
                    InputOption::VALUE_REQUIRED,
                    'If specified, use the given directory as patch directory.',
                    'patches'
                ),
                new InputOption(
                    'tests',
                    't',
                    InputOption::VALUE_NONE,
                    'Also apply changes of tests. This results in installing the sources for the affected packages.'
                ),
            ])
        ;
    }

    /**
     * {@inheritDoc}
     *
     * @see https://review.typo3.org/Documentation/rest-api-changes.html#change-id
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        // Get parameters
        if (!is_array($changeIds = $input->getArgument('change-id'))) {
            throw new UnexpectedValueException('Invalid change IDs.', 1640855652);
        }

        if (!is_string($destination = $input->getOption('patch-dir'))) {
            throw new UnexpectedValueException('Invalid patch-dir.', 1640855653);
        }

        if (!is_bool($includeTests = $input->getOption('tests'))) {
            throw new UnexpectedValueException('Invalid tests option.', 1640855654);
        }

        // Get Composer instance
        if (!($composer = $this->getComposer(true)) instanceof Composer) {
            throw new UnexpectedValueException('Invalid Composer instance.', 1640857365);
        }

        $config = $composer->getConfig();
        $io = $this->getIO();

        $configFile = new JsonFile(Factory::getComposerFile(), null, $io);
        $configSource = new JsonConfigSource($configFile);

        $currentConfig = $configFile->read();
        $extraPatches = [];

        if (
            is_array($currentConfig)
            && is_array($currentConfig['extra'] ?? null)
            && is_array($currentConfig['extra']['patches'] ?? null)
        ) {
            $extraPatches = $currentConfig['extra']['patches'];
        }

        $gerrit = new Gerrit(Factory::createHttpDownloader($io, $config));
        $patchCreator = new PatchCreator($composer, $io);

        $application = new Application();
        $application->setAutoExit(false);

        // Process change IDs
        $composerChanges = [];
        $patchCount = 0;

        foreach ($changeIds as $changeId) {
            $io->write(sprintf('<info>Preparing patch for change <comment>%s</comment></info>', $changeId));

            try {
                $subject = $gerrit->getSubject($changeId);
                $io->write(sprintf('  - Subject is <comment>%s</comment>', $subject));
            } catch (RuntimeException | UnexpectedValueException $th) {
                $io->writeError('<warning>Error getting subject</warning>');
                $io->writeError(sprintf(
                    '<warning>%s</warning>',
                    $th->getMessage()
                ), true, IOInterface::VERBOSE);

                continue;
            }

            try {
                $numericId = $gerrit->getNumericId($changeId);
                $io->write(sprintf('  - Numeric ID is <comment>%s</comment>', $numericId));
            } catch (RuntimeException | UnexpectedValueException $th) {
                $io->writeError('<warning>Error getting numeric ID</warning>');
                $io->writeError(sprintf(
                    '<warning>%s</warning>',
                    $th->getMessage()
                ), true, IOInterface::VERBOSE);

                continue;
            }

            $currentComposerChanges = $patchCreator->create(
                $numericId,
                $subject,
                $gerrit->getPatch($changeId),
                $destination,
                $includeTests
            );
            $io->write(sprintf(
                '  - Change saved to <comment>%d</comment> patch%s',
                count($currentComposerChanges),
                count($currentComposerChanges) === 1 ? '' : 'es'
            ));

            if ($currentComposerChanges !== []) {
                $composerChanges = array_merge(
                    $composerChanges,
                    $currentComposerChanges
                );

                $patchCount += 1;
            }
        }

        // Add new patches to the composer.json
        if ($composerChanges !== []) {
            $io->write('<info>Adding patches to composer.json</info>');
            $extraPatches = array_merge($extraPatches, $composerChanges);
            $preferredInstallChanged = [];

            foreach ($composerChanges as $packageName => $patches) {
                // Adding patches for package
                $io->write(sprintf('  - Adding patches for <info>%s</info>', $packageName));
                $configSource->addProperty('extra.patches.' . $packageName, $extraPatches[$packageName]);

                // Reconfigure preferred-install for package if changes of tests are patched too
                if ($includeTests) {
                    $io->write(sprintf(
                        '  - Reconfiguring <info>preferred-install</info> for package <info>%s</info>',
                        $packageName
                    ));
                    $configSource->addConfigSetting(
                        'preferred-install.' . $packageName,
                        'source'
                    );
                    $preferredInstallChanged[] = $packageName;
                }
            }

            if ($preferredInstallChanged !== []) {
                $configSource->addProperty(
                    'extra.gilbertsoft/typo3-core-patches.preferred-install-changed',
                    $preferredInstallChanged
                );
            }
        } else {
            $io->write('<warning>No patches created</warning>');
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
            throw new RuntimeException('Error while updating the Composer lock file.', 1641257927);
        }

        $io->write(sprintf(
            '<info>%d TYPO3 core patch%s added</info>',
            $patchCount,
            $patchCount === 1 ? '' : 'es'
        ));

        return 0;
    }
}
