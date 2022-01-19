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

namespace GsTYPO3\CorePatches\Tests\Unit\Config;

use GsTYPO3\CorePatches\Config\Change;
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
        int $revision,
        iterable $packages,
        bool $tests
    ): void {
        self::assertSame($number, $change->getNumber());
        self::assertSame($revision, $change->getRevision());

        foreach ($packages as $package) {
            self::assertTrue($change->getPackages()->has($package));
        }

        self::assertSame($tests, $change->getTests());
    }

    public function testMinimalArguments(): void
    {
        $this->assertProperties(
            new Change(12345),
            12345,
            -1,
            [],
            false
        );
    }

    /**
     * @dataProvider scenariosProvider
     *
     * @param array<int, string> $packages
     */
    public function testScenarios(
        int $number,
        int $revision,
        iterable $packages,
        bool $tests
    ): void {
        $this->assertProperties(
            new Change($number, $revision, $packages, $tests),
            $number,
            $revision,
            $packages,
            $tests
        );
    }

    /**
     * @return Iterator<string, array<string, array<int, string>|int|string|bool>>
     */
    public function scenariosProvider(): Iterator
    {
        yield 'all arguments are used' => [
            'number' => 12345,
            'revision' => 1,
            'packages' => [
                'package1',
                'package2',
                'package3',
            ],
            'tests' => true,
        ];
        yield 'inverted package order' => [
            'number' => 12345,
            'revision' => 1,
            'packages' => [
                'package3',
                'package2',
                'package1',
            ],
            'tests' => false,
        ];
    }

    public function testJsonSerialize(): void
    {
        self::assertSame(
            [
                'number' => 12345,
                'packages' => [],
            ],
            (new Change(12345))->jsonSerialize()
        );

        self::assertSame(
            [
                'number' => 12345,
                'revision' => 1,
                'packages' => [],
            ],
            (new Change(12345, 1))->jsonSerialize()
        );

        self::assertSame(
            [
                'number' => 12345,
                'revision' => 1,
                'packages' => [],
                'tests' => true,
            ],
            (new Change(12345, 1, [], true))->jsonSerialize()
        );
    }

    public function testJsonUnserialize(): void
    {
        $this->assertProperties(
            (new Change(0))->jsonUnserialize(
                [
                    'number' => 12345,
                    'packages' => ['package1', 'package2', 'package3'],
                ]
            ),
            12345,
            -1,
            ['package1', 'package2', 'package3'],
            false
        );

        $this->assertProperties(
            (new Change(0))->jsonUnserialize(
                [
                    'number' => 12345,
                    'revision' => 1,
                    'packages' => ['package'],
                ]
            ),
            12345,
            1,
            ['package'],
            false
        );

        $this->assertProperties(
            (new Change(0))->jsonUnserialize(
                [
                    'number' => 12345,
                    'packages' => ['package'],
                    'tests' => true,
                    ]
            ),
            12345,
            -1,
            ['package'],
            true
        );

        $this->assertProperties(
            (new Change(0))->jsonUnserialize(
                [
                    'number' => 12345,
                    'revision' => 1,
                    'packages' => ['package1', 'package2', 'package3'],
                    'tests' => true,
                    ]
            ),
            12345,
            1,
            ['package1', 'package2', 'package3'],
            true
        );

        $change = new Change(0);
        self::assertSame(
            $change,
            $change->jsonUnserialize([
                'number' => 12345,
                'packages' => ['package'],
            ])
        );
    }

    public function testJsonUnserializeThrowsOnMissingNumber(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Number "NULL" is not numeric or missing.');
        (new Change(0))->jsonUnserialize([]);
    }

    public function testJsonUnserializeThrowsOnMissingPackages(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Packages is not an array or missing.');
        (new Change(0))->jsonUnserialize(['number' => 12345]);
    }
}
