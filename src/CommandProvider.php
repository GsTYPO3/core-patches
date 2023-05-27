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
use GsTYPO3\CorePatches\Command\Typo3\Patch\ApplyCommand;
use GsTYPO3\CorePatches\Command\Typo3\Patch\RemoveCommand;
use GsTYPO3\CorePatches\Command\Typo3\Patch\UpdateCommand;

final class CommandProvider implements CommandProviderCapability
{
    /**
     * @inheritDoc
     * @return ApplyCommand[]|RemoveCommand[]|UpdateCommand[]
     */
    public function getCommands(): array
    {
        return [
            new ApplyCommand(),
            new RemoveCommand(),
            new UpdateCommand(),
        ];
    }
}
