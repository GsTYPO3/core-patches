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

namespace GsTYPO3\CorePatches\Tests\Unit\Command;

use GsTYPO3\CorePatches\Command\Typo3\Patch\ApplyCommand;
use RuntimeException;
use Throwable;

/**
 * @ covers \GsTYPO3\CorePatches\Command\Typo3\Patch\ApplyCommand
 * @ uses \GsTYPO3\CorePatches\CommandProvider
 * @ uses \GsTYPO3\CorePatches\Command\Typo3\Patch\RemoveCommand
 * @ uses \GsTYPO3\CorePatches\Command\Typo3\Patch\UpdateCommand
 * @ uses \GsTYPO3\CorePatches\Config
 * @ uses \GsTYPO3\CorePatches\Config\Changes
 * @ uses \GsTYPO3\CorePatches\Config\Changes\Change
 * @ uses \GsTYPO3\CorePatches\Config\Packages
 * @ uses \GsTYPO3\CorePatches\Config\Patches
 * @ uses \GsTYPO3\CorePatches\Config\Patches\PackagePatches
 * @ uses \GsTYPO3\CorePatches\Config\PreferredInstall
 * @ uses \GsTYPO3\CorePatches\Gerrit\Entity\AbstractEntity
 * @ uses \GsTYPO3\CorePatches\Gerrit\Entity\ChangeInfo
 * @ uses \GsTYPO3\CorePatches\Gerrit\Entity\IncludedInInfo
 * @ uses \GsTYPO3\CorePatches\Gerrit\RestApi
 * @ uses \GsTYPO3\CorePatches\Plugin
 * @ uses \GsTYPO3\CorePatches\Utility\ComposerUtils
 * @ uses \GsTYPO3\CorePatches\Utility\PatchUtils
 */
final class ApplyCommandTest extends CommandTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->getApplication()->add(new ApplyCommand());
    }

    /**
     * @param  array<int, string>|null                  $changeIds
     * @param  array<string, string|string[]|bool|null> $input
     * @return array<string, string|string[]|bool|null>
     */
    private function getInput(
        ?array $changeIds = null,
        ?string $patchDir = null,
        ?bool $tests = null,
        array $input = []
    ): array {
        $result = [];

        if ($changeIds !== null) {
            $result['change-id'] = $changeIds;
        }

        if ($patchDir !== null) {
            $result['--patch-dir'] = $patchDir;
        }

        if ($tests !== null) {
            $result['--tests'] = $tests;
        }

        return array_merge(
            //parent::getInput($input),
            $result,
            $input
        );
    }

    public function testExecute(): void
    {
        $commandTester = $this->getCommandTester('typo3:patch:apply');

        try {
            // test default path argument
            $commandTester->execute($this->getInput(['73021']));
            $commandTester->assertCommandIsSuccessful();

            $display = $commandTester->getDisplay();
            self::assertStringContainsString('2 TYPO3 core patches added', $display);
        } catch (Throwable $throwable) {
            throw new RuntimeException($commandTester->getDisplay(), 0, $throwable);
        }
    }
}
