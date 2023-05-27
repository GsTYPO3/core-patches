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

namespace GsTYPO3\CorePatches\Tests\Unit;

use Composer\Command\BaseCommand;
use GsTYPO3\CorePatches\CommandProvider;

/**
 * @covers \GsTYPO3\CorePatches\CommandProvider
 * @uses \GsTYPO3\CorePatches\Command\Typo3\Patch\ApplyCommand
 * @uses \GsTYPO3\CorePatches\Command\Typo3\Patch\RemoveCommand
 * @uses \GsTYPO3\CorePatches\Command\Typo3\Patch\UpdateCommand
 * @uses \GsTYPO3\CorePatches\Config
 * @uses \GsTYPO3\CorePatches\Config\Changes
 * @uses \GsTYPO3\CorePatches\Config\Packages
 * @uses \GsTYPO3\CorePatches\Config\Patches
 * @uses \GsTYPO3\CorePatches\Config\PreferredInstall
 */
final class CommandProviderTest extends TestCase
{
    public function testCommands(): void
    {
        $commandProvider = new CommandProvider();
        self::assertContainsOnlyInstancesOf(BaseCommand::class, $commandProvider->getCommands());
        self::assertCount(3, $commandProvider->getCommands());
    }
}
