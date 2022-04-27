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

use GsTYPO3\CorePatches\Command\Typo3\Patch\RemoveCommand;

final class RemoveCommandTest extends CommandTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->getApplication()->add(new RemoveCommand());
    }

    /**
     * @param  array<int, string>|null                  $changeIds
     * @param  array<string, string|string[]|bool|null> $input
     * @return array<string, string|string[]|bool|null>
     */
    private function getInput(
        ?array $changeIds = null,
        array $input = []
    ): array {
        $result = [];

        if ($changeIds !== null) {
            $result['change-id'] = $changeIds;
        }

        return array_merge(
            //parent::getInput($input),
            $result,
            $input
        );
    }

    public function testExecute(): void
    {
        $commandTester = $this->getCommandTester('typo3:patch:remove');

        // test default path argument
        $commandTester->execute($this->getInput(['73021']));
        $commandTester->assertCommandIsSuccessful();

        $display = $commandTester->getDisplay();
        self::assertStringContainsString('0 TYPO3 core patches removed', $display);
    }
}
