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
use GsTYPO3\CorePatches\Exception\InvalidArgumentException;
use GsTYPO3\CorePatches\Exception\UnexpectedValueException;
use GsTYPO3\CorePatches\Utility\ComposerUtils;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

final class RemoveCommand extends BaseCommand
{
    /**
     * @inheritDoc
     */
    protected function configure(): void
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
     * @inheritDoc
     *
     * @throws InvalidArgumentException
     * @throws UnexpectedValueException
     */
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        // Get parameters
        if (!is_array($changeIds = $input->getArgument('change-id'))) {
            // @codeCoverageIgnoreStart
            throw new InvalidArgumentException('Invalid change IDs.');
            // @codeCoverageIgnoreEnd
        }

        // Get Composer instance
        if (!($composer = $this->getComposer(true)) instanceof Composer) {
            // @codeCoverageIgnoreStart
            throw new UnexpectedValueException('Invalid Composer instance.', 1_640_857_366);
            // @codeCoverageIgnoreEnd
        }

        $io = $this->getIO();
        $composerUtils = new ComposerUtils($composer, $io);

        $io->write('<info>Removing TYPO3 core patches...</info>');

        // Remove patches and affected packages, update lock file and reinstall packages
        $patchesCount = $composerUtils->removePatches($changeIds);
        $composerUtils->updateLock($output);

        $io->write(sprintf(
            '<info>%d TYPO3 core patch%s removed</info>',
            $patchesCount,
            $patchesCount === 1 ? '' : 'es'
        ));

        return 0;
    }
}
