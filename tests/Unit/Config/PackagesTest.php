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

use GsTYPO3\CorePatches\Config;
use GsTYPO3\CorePatches\Config\Packages;
use GsTYPO3\CorePatches\Exception\UnexpectedValueException;
use GsTYPO3\CorePatches\Tests\Unit\TestCase;

final class PackagesTest extends TestCase
{
    public function testItemsAreAddedDuringConstruction(): void
    {
        $config = new Config();

        self::assertCount(3, new Packages($config, ['package1', 'package2', 'package3']));
    }

    public function testItemsAreReordered(): void
    {
        $config = new Config();

        self::assertSame(
            ['package1', 'package2', 'package3'],
            (new Packages($config, ['package3', 'package2', 'package1']))->jsonSerialize()
        );
    }

    public function testHasAndFind(): void
    {
        $config = new Config();

        $packages = new Packages($config, ['package']);

        self::assertTrue($packages->has('package'));
        self::assertFalse($packages->has('invalid-package'));
    }

    public function testIsEmpty(): void
    {
        $config = new Config();

        self::assertTrue((new Packages($config))->isEmpty());
        self::assertFalse((new Packages($config, ['package']))->isEmpty());
    }

    public function testRemove(): void
    {
        $config = new Config();

        self::assertNull((new Packages($config))->remove('invalid-package'));
        self::assertSame('package', (new Packages($config, ['package']))->remove('package'));

        $packages = new Packages($config, ['package3', 'package2', 'package1']);

        self::assertSame('package2', $packages->remove('package2'));
        self::assertSame(
            ['package1', 'package3'],
            $packages->jsonSerialize()
        );

        self::assertSame('package1', $packages->remove('package1'));
        self::assertSame(
            ['package3'],
            $packages->jsonSerialize()
        );
    }

    public function testGetConfig(): void
    {
        $config = new Config();

        self::assertSame(
            $config,
            (new Packages($config))->getConfig()
        );
    }

    public function testJsonSerialize(): void
    {
        $config = new Config();

        self::assertSame(
            ['package1', 'package2', 'package3'],
            (new Packages($config, ['package3', 'package2', 'package1']))->jsonSerialize()
        );
    }

    public function testJsonUnserialize(): void
    {
        $config = new Config();

        $packages = new Packages($config);

        self::assertSame(
            ['package1', 'package2', 'package3'],
            $packages->jsonUnserialize(['package3', 'package2', 'package1'])->jsonSerialize()
        );

        self::assertSame(
            ['package'],
            $packages->jsonUnserialize(['package'])->jsonSerialize()
        );

        self::assertSame(
            $packages,
            $packages->jsonUnserialize(['package'])
        );
    }

    public function testJsonUnserializeThrowsOnInvalidType(): void
    {
        $config = new Config();

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Package is not a string (1).');
        (new Packages($config))->jsonUnserialize([1]);
    }
}
