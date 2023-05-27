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

use GsTYPO3\CorePatches\Config\PreferredInstall;
use GsTYPO3\CorePatches\Exception\UnexpectedValueException;
use GsTYPO3\CorePatches\Tests\Unit\TestCase;

/**
 * @covers \GsTYPO3\CorePatches\Config\PreferredInstall
 */
final class PreferredInstallTest extends TestCase
{
    public function testItemsAreAddedDuringConstruction(): void
    {
        self::assertCount(3, new PreferredInstall([
            'package1' => 'install-method1',
            'package2' => 'install-method2',
            'package3' => 'install-method3',
        ]));
    }

    public function testItemsAreReordered(): void
    {
        self::assertSame(
            [
                'package1' => 'install-method1',
                'package2' => 'install-method2',
                'package3' => 'install-method3',
            ],
            (new PreferredInstall([
                'package1' => 'install-method1',
                'package2' => 'install-method2',
                'package3' => 'install-method3',
            ]))->jsonSerialize()
        );
    }

    public function testHas(): void
    {
        self::assertFalse((new PreferredInstall())->has('package', 'install-method'));
        self::assertTrue(
            (new PreferredInstall(['package' => 'install-method']))->has('package', 'install-method')
        );
    }

    public function testIsEmpty(): void
    {
        self::assertTrue((new PreferredInstall())->isEmpty());
        self::assertFalse((new PreferredInstall(['package' => 'install-method']))->isEmpty());
    }

    public function testRemove(): void
    {
        self::assertNull((new PreferredInstall())->remove('invalid-description'));
        self::assertSame(['package' => 'install-method'], (new PreferredInstall([
            'package' => 'install-method',
        ]))->remove('package'));

        $preferredInstall = new PreferredInstall([
            'package1' => 'install-method1',
            'package2' => 'install-method2',
            'package3' => 'install-method3',
        ]);

        self::assertSame(['package2' => 'install-method2'], $preferredInstall->remove('package2'));
        self::assertSame(
            [
                'package1' => 'install-method1',
                'package3' => 'install-method3',
            ],
            $preferredInstall->jsonSerialize()
        );

        self::assertSame(['package1' => 'install-method1'], $preferredInstall->remove('package1'));
        self::assertSame(
            [
                'package3' => 'install-method3',
            ],
            $preferredInstall->jsonSerialize()
        );

        self::assertSame(['package3' => 'install-method3'], $preferredInstall->remove('package3'));
        self::assertSame(
            [],
            $preferredInstall->jsonSerialize()
        );
    }

    public function testJsonSerialize(): void
    {
        self::assertSame(
            [
                'package1' => 'install-method1',
                'package2' => 'install-method2',
                'package3' => 'install-method3',
            ],
            (new PreferredInstall([
                'package1' => 'install-method1',
                'package2' => 'install-method2',
                'package3' => 'install-method3',
            ]))->jsonSerialize()
        );
    }

    public function testJsonUnserialize(): void
    {
        $preferredInstall = new PreferredInstall();

        self::assertSame(
            [
                'package1' => 'install-method1',
                'package2' => 'install-method2',
                'package3' => 'install-method3',
            ],
            $preferredInstall->jsonUnserialize([
                'package1' => 'install-method1',
                'package2' => 'install-method2',
                'package3' => 'install-method3',
            ])->jsonSerialize()
        );

        self::assertSame(
            ['package' => 'install-method'],
            $preferredInstall->jsonUnserialize(['package' => 'install-method'])->jsonSerialize()
        );

        self::assertSame(
            $preferredInstall,
            $preferredInstall->jsonUnserialize(['package' => 'install-method'])
        );
    }

    public function testJsonUnserializeThrowsOnInvalidPackageType(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Package is not a string (1).');
        (new PreferredInstall())->jsonUnserialize([1 => 'install-method']);
    }

    public function testJsonUnserializeThrowsOnInvalidInstallMethodType(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Install method is not a string (1).');
        (new PreferredInstall())->jsonUnserialize(['package' => 1]);
    }
}
