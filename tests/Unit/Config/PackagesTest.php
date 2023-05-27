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

use GsTYPO3\CorePatches\Config\Packages;
use GsTYPO3\CorePatches\Exception\UnexpectedValueException;
use GsTYPO3\CorePatches\Tests\Unit\TestCase;

/**
 * @covers \GsTYPO3\CorePatches\Config\Packages
 */
final class PackagesTest extends TestCase
{
    public function testItemsAreAddedDuringConstruction(): void
    {
        self::assertCount(3, new Packages(['package1', 'package2', 'package3']));
    }

    public function testItemsAreReordered(): void
    {
        self::assertSame(
            ['package1', 'package2', 'package3'],
            (new Packages(['package3', 'package2', 'package1']))->jsonSerialize()
        );
    }

    public function testHasAndFind(): void
    {
        $packages = new Packages(['package']);

        self::assertTrue($packages->has('package'));
        self::assertFalse($packages->has('invalid-package'));
    }

    public function testIsEmpty(): void
    {
        self::assertTrue((new Packages())->isEmpty());
        self::assertFalse((new Packages(['package']))->isEmpty());
    }

    public function testRemove(): void
    {
        self::assertNull((new Packages())->remove('invalid-package'));
        self::assertSame('package', (new Packages(['package']))->remove('package'));

        $packages = new Packages(['package3', 'package2', 'package1']);

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

    public function testJsonSerialize(): void
    {
        self::assertSame(
            ['package1', 'package2', 'package3'],
            (new Packages(['package3', 'package2', 'package1']))->jsonSerialize()
        );
    }

    public function testJsonUnserialize(): void
    {
        $packages = new Packages();

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
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Package is not a string (1).');
        (new Packages())->jsonUnserialize([1]);
    }
}
