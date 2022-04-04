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
use GsTYPO3\CorePatches\Config\PreferredInstall;
use GsTYPO3\CorePatches\Exception\UnexpectedValueException;
use GsTYPO3\CorePatches\Tests\Unit\TestCase;

final class PreferredInstallTest extends TestCase
{
    public function testItemsAreAddedDuringConstruction(): void
    {
        $config = new Config();

        self::assertCount(3, new PreferredInstall($config, [
            'package1' => 'install-method1',
            'package2' => 'install-method2',
            'package3' => 'install-method3',
        ]));
    }

    public function testItemsAreReordered(): void
    {
        $config = new Config();

        self::assertSame(
            [
                'package1' => 'install-method1',
                'package2' => 'install-method2',
                'package3' => 'install-method3',
            ],
            (new PreferredInstall($config, [
                'package1' => 'install-method1',
                'package2' => 'install-method2',
                'package3' => 'install-method3',
            ]))->jsonSerialize()
        );
    }

    public function testIsEmpty(): void
    {
        $config = new Config();

        self::assertTrue((new PreferredInstall($config))->isEmpty());
        self::assertFalse((new PreferredInstall($config, ['package' => 'install-method']))->isEmpty());
    }

    public function testRemove(): void
    {
        $config = new Config();

        self::assertNull((new PreferredInstall($config))->remove('invalid-description'));
        self::assertSame('install-method', (new PreferredInstall($config, [
            'package' => 'install-method',
        ]))->remove('package'));

        $preferredInstall = new PreferredInstall($config, [
            'package1' => 'install-method1',
            'package2' => 'install-method2',
            'package3' => 'install-method3',
        ]);

        self::assertSame('install-method2', $preferredInstall->remove('package2'));
        self::assertSame(
            [
                'package1' => 'install-method1',
                'package2' => '',
                'package3' => 'install-method3',
            ],
            $preferredInstall->jsonSerialize()
        );

        self::assertSame('install-method1', $preferredInstall->remove('package1'));
        self::assertSame(
            [
                'package1' => '',
                'package2' => '',
                'package3' => 'install-method3',
            ],
            $preferredInstall->jsonSerialize()
        );
    }

    public function testGetConfig(): void
    {
        $config = new Config();

        self::assertSame(
            $config,
            (new PreferredInstall($config))->getConfig()
        );
    }

    public function testJsonSerialize(): void
    {
        $config = new Config();

        self::assertSame(
            [
                'package1' => 'install-method1',
                'package2' => 'install-method2',
                'package3' => 'install-method3',
            ],
            (new PreferredInstall($config, [
                'package1' => 'install-method1',
                'package2' => 'install-method2',
                'package3' => 'install-method3',
            ]))->jsonSerialize()
        );
    }

    public function testJsonUnserialize(): void
    {
        $config = new Config();

        $preferredInstall = new PreferredInstall($config);

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
        $config = new Config();

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Package is not a string (1).');
        (new PreferredInstall($config))->jsonUnserialize([1 => 'install-method']);
    }

    public function testJsonUnserializeThrowsOnInvalidInstallMethodType(): void
    {
        $config = new Config();

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Install method is not a string (1).');
        (new PreferredInstall($config))->jsonUnserialize(['package' => 1]);
    }
}
