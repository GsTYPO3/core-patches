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

namespace GsTYPO3\CorePatches;

use Composer\Plugin\Capability\CommandProvider as CommandProviderCapability;

final class CommandProvider implements CommandProviderCapability
{
    /**
     * {@inheritDoc}
     * @return Command\Typo3\Patch\ApplyCommand[]|Command\Typo3\Patch\RemoveCommand[]
     */
    public function getCommands(): array
    {
        return [
            new Command\Typo3\Patch\ApplyCommand(),
            new Command\Typo3\Patch\RemoveCommand(),
        ];
    }
}
