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
use GsTYPO3\CorePatches\Config;
use GsTYPO3\CorePatches\Exception\InvalidArgumentException;
use GsTYPO3\CorePatches\Exception\InvalidOptionException;
use GsTYPO3\CorePatches\Exception\UnexpectedValueException;
use GsTYPO3\CorePatches\Utility\ComposerUtils;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

final class ApplyCommand extends BaseCommand
{
    /**
     * @inheritDoc
     */
    protected function configure(): void
    {
        $patchDirectory = (new Config())->load()->getPatchDirectory();

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
                    $patchDirectory
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
     * @inheritDoc
     *
     * @throws InvalidArgumentException
     * @throws UnexpectedValueException
     *
     * @see https://review.typo3.org/Documentation/rest-api-changes.html#change-id
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {

        // Get parameters
        if (!is_array($changeIds = $input->getArgument('change-id'))) {
            // @codeCoverageIgnoreStart
            throw new InvalidArgumentException('Invalid change IDs.');
            // @codeCoverageIgnoreEnd
        }

        if (!is_string($destination = $input->getOption('patch-dir'))) {
            // @codeCoverageIgnoreStart
            throw new InvalidOptionException('Invalid patch-dir.');
            // @codeCoverageIgnoreEnd
        }

        if (!is_bool($includeTests = $input->getOption('tests'))) {
            // @codeCoverageIgnoreStart
            throw new InvalidOptionException('Invalid tests option.');
            // @codeCoverageIgnoreEnd
        }

        // Get Composer instance
        if (!($composer = $this->getComposer(true)) instanceof Composer) {
            // @codeCoverageIgnoreStart
            throw new UnexpectedValueException('Invalid Composer instance.', 1_640_857_365);
            // @codeCoverageIgnoreEnd
        }

        $io = $this->getIO();
        $composerUtils = new ComposerUtils($composer, $io);

        $io->write('<info>Adding TYPO3 core patches...</info>');

        // Add patches, update lock file and apply patches
        $patchesCount = $composerUtils->addPatches($changeIds, $destination, $includeTests);
        $composerUtils->updateLock($output);

        $io->write(sprintf(
            '<info>%d TYPO3 core patch%s added</info>',
            $patchesCount,
            $patchesCount === 1 ? '' : 'es'
        ));

        return 0;
    }
}
