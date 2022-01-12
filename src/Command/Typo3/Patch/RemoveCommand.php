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
     *
     * @throws InvalidArgumentException
     * @throws UnexpectedValueException
     */
    public function execute(InputInterface $input, OutputInterface $output)
    {
        // Get parameters
        if (!is_array($changeIds = $input->getArgument('change-id'))) {
            throw new InvalidArgumentException('Invalid change IDs.');
        }

        // Get Composer instance
        if (!($composer = $this->getComposer(true)) instanceof Composer) {
            throw new UnexpectedValueException('Invalid Composer instance.', 1640857366);
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
