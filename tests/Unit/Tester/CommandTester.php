<?php

/*
 * This file is part of TYPO3 Core Patches.
 *
 * (c) Gilbertsoft LLC (gilbertsoft.org)
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace GsTYPO3\CorePatches\Tests\Unit\Tester;

use Composer\Command\BaseCommand;
use Composer\IO\BufferIO;
use GsTYPO3\CorePatches\Tests\Unit\Tester\Constraint\CommandIsSuccessful;
use PHPUnit\Framework\Assert;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester as BaseCommandTester;

/**
 * Wrapper for the Symfony CommandTester.
 *
 * @see \Symfony\Component\Console\Tester\CommandTester
 */
final class CommandTester
{
    private BaseCommandTester $baseCommandTester;

    private BufferIO $bufferIO;

    public function __construct(Command $command)
    {
        $this->bufferIO = new BufferIO();

        if ($command instanceof BaseCommand) {
            $command->setIO($this->bufferIO);
        }

        $this->baseCommandTester = new BaseCommandTester($command);
    }

    /**
     * @param array<string, array<string>|bool|string|null> $input
     * @param array<string, mixed>                          $options
     */
    public function execute(array $input, array $options = []): int
    {
        return $this->baseCommandTester->execute($input, $options);
    }

    public function getDisplay(bool $normalize = false): string
    {
        $display = $this->baseCommandTester->getDisplay($normalize);
        $display .= $this->bufferIO->getOutput();

        if ($normalize) {
            return str_replace(\PHP_EOL, "\n", $display);
        }

        return $display;
    }

    public function getInput(): InputInterface
    {
        return $this->baseCommandTester->getInput();
    }

    public function getOutput(): OutputInterface
    {
        return $this->baseCommandTester->getOutput();
    }

    public function getStatusCode(): int
    {
        return $this->baseCommandTester->getStatusCode();
    }

    public function assertCommandIsSuccessful(string $message = ''): void
    {
        Assert::assertThat($this->getStatusCode(), new CommandIsSuccessful(), $message);
    }
}
