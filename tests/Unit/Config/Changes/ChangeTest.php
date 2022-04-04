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

namespace GsTYPO3\CorePatches\Tests\Unit\Config\Changes;

use GsTYPO3\CorePatches\Config;
use GsTYPO3\CorePatches\Config\Changes\Change;
use GsTYPO3\CorePatches\Exception\UnexpectedValueException;
use GsTYPO3\CorePatches\Tests\Unit\TestCase;
use Iterator;

final class ChangeTest extends TestCase
{
    /**
     * @param iterable<int, string> $packages
     */
    private function assertProperties(
        Change $change,
        int $number,
        iterable $packages,
        bool $tests,
        string $patchDirectory,
        int $revision
    ): void {
        if ($patchDirectory === '') {
            $patchDirectory = $change->getConfig()->getPatchDirectory();
        }

        self::assertSame($number, $change->getNumber());

        foreach ($packages as $package) {
            self::assertTrue($change->getPackages()->has($package));
        }

        self::assertSame($tests, $change->getTests());
        self::assertSame($patchDirectory, $change->getPatchDirectory());
        self::assertSame($revision, $change->getRevision());
    }

    public function testMinimalArguments(): void
    {
        $config = new Config();

        $this->assertProperties(
            new Change($config, 12345),
            12345,
            [],
            false,
            '',
            -1
        );
    }

    /**
     * @dataProvider scenariosProvider
     *
     * @param array<int, string> $packages
     */
    public function testScenarios(
        int $number,
        iterable $packages,
        bool $tests,
        string $patchDirectory,
        int $revision
    ): void {
        $config = new Config();

        $this->assertProperties(
            new Change($config, $number, $packages, $tests, $patchDirectory, $revision),
            $number,
            $packages,
            $tests,
            $patchDirectory,
            $revision
        );
    }

    /**
     * @return Iterator<string, array<string, array<int, string>|int|string|bool>>
     */
    public function scenariosProvider(): Iterator
    {
        yield 'all arguments are used' => [
            'number' => 12345,
            'packages' => [
                'package1',
                'package2',
                'package3',
            ],
            'tests' => true,
            'patchDirectory' => 'core-patches',
            'revision' => 1,
        ];
        yield 'inverted package order' => [
            'number' => 12345,
            'packages' => [
                'package3',
                'package2',
                'package1',
            ],
            'tests' => false,
            'patchDirectory' => '',
            'revision' => -1,
        ];
    }

    public function testGetConfig(): void
    {
        $config = new Config();

        self::assertSame(
            $config,
            (new Change($config, 1))->getConfig()
        );
    }

    public function testJsonSerialize(): void
    {
        $config = new Config();

        self::assertSame(
            [
                'packages' => [],
            ],
            (new Change($config, 12345))->jsonSerialize()
        );

        self::assertSame(
            [
                'revision' => 1,
                'packages' => [],
            ],
            (new Change($config, 12345, [], false, '', 1))->jsonSerialize()
        );

        self::assertSame(
            [
                'revision' => 1,
                'packages' => [],
                'tests' => true,
            ],
            (new Change($config, 12345, [], true, '', 1))->jsonSerialize()
        );

        self::assertSame(
            [
                'revision' => 1,
                'packages' => [],
                'tests' => true,
                'patch-directory' => 'patch-dir',
            ],
            (new Change($config, 12345, [], true, 'patch-dir', 1))->jsonSerialize()
        );
    }

    public function testJsonUnserialize(): void
    {
        $config = new Config();

        $this->assertProperties(
            (new Change($config, 0))->jsonUnserialize(
                [
                    'packages' => ['package1', 'package2', 'package3'],
                ]
            ),
            0,
            ['package1', 'package2', 'package3'],
            false,
            '',
            -1
        );

        $this->assertProperties(
            (new Change($config, 0))->jsonUnserialize(
                [
                    'revision' => 1,
                    'packages' => ['package'],
                ]
            ),
            0,
            ['package'],
            false,
            '',
            1
        );

        $this->assertProperties(
            (new Change($config, 0))->jsonUnserialize(
                [
                    'packages' => ['package'],
                    'tests' => true,
                ]
            ),
            0,
            ['package'],
            true,
            '',
            -1
        );

        $this->assertProperties(
            (new Change($config, 0))->jsonUnserialize(
                [
                    'packages' => ['package'],
                    'patch-directory' => 'patch-dir',
                ]
            ),
            0,
            ['package'],
            false,
            'patch-dir',
            -1
        );

        $this->assertProperties(
            (new Change($config, 0))->jsonUnserialize(
                [
                    'revision' => 1,
                    'packages' => ['package1', 'package2', 'package3'],
                    'tests' => true,
                    'patch-directory' => 'patch-dir',
                ]
            ),
            0,
            ['package1', 'package2', 'package3'],
            true,
            'patch-dir',
            1,
        );

        $change = new Change($config, 0);
        self::assertSame(
            $change,
            $change->jsonUnserialize([
                'packages' => ['package'],
            ])
        );
    }

    public function testJsonUnserializeThrowsOnMissingPackages(): void
    {
        $config = new Config();

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Packages is not an array or missing.');
        (new Change($config, 0))->jsonUnserialize(['number' => 12345]);
    }
}
