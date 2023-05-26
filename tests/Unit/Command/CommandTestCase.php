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

use Composer\Console\Application;
use GsTYPO3\CorePatches\Tests\Unit\TestCase;
use GsTYPO3\CorePatches\Tests\Unit\Tester\CommandTester;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

abstract class CommandTestCase extends TestCase
{
    private Application $application;

    private string $previousWorkingDir;

    private string $testWorkingDir;

    protected function setUp(): void
    {
        parent::setUp();

        $this->application = new Application();
        $this->application->setAutoExit(false);

        if (($previousWorkingDir = getcwd()) === false) {
            throw new RuntimeException('Unable to determine current directory.', 1_636_451_408);
        }

        $this->previousWorkingDir = $previousWorkingDir;
        $this->testWorkingDir = self::getTestPath();
        chdir($this->testWorkingDir);

        self::createFiles($this->testWorkingDir, [
            'composer.json' => 'FIX:composer.json',
        ]);

        $bufferedOutput = new BufferedOutput();
        $this->application->run(
            new ArrayInput([
                'command' => 'install',
                '--no-dev' => true,
                '--no-progress' => true,
                '--ansi' => true,
                '--no-interaction' => true,
            ]),
            $bufferedOutput
        );
    }

    protected function tearDown(): void
    {
        chdir($this->previousWorkingDir);

        parent::tearDown();
    }

    protected function getApplication(): Application
    {
        return $this->application;
    }

    protected function getCommand(string $name): Command
    {
        return $this->application->find($name);
    }

    protected function getCommandTester(string $commandName): CommandTester
    {
        return new CommandTester($this->getCommand($commandName));
    }

    /**
     * @ param array<string, string|string[]|bool|null> $input
     * @ return array<string, string|string[]|bool|null>
     * /
    protected function getInput(array $input = []): array
    {
        return $input;
    }
    */
}
