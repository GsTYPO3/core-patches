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

namespace GsTYPO3\CorePatches\Tests\Unit\Config\Patches;

use GsTYPO3\CorePatches\Config;
use GsTYPO3\CorePatches\Config\Patches\PackagePatches;
use GsTYPO3\CorePatches\Exception\UnexpectedValueException;
use GsTYPO3\CorePatches\Tests\Unit\TestCase;

final class PackagePatchesTest extends TestCase
{
    public function testItemsAreAddedDuringConstruction(): void
    {
        $config = new Config();

        self::assertCount(3, new PackagePatches($config, [
            'description1' => 'patch1',
            'description2' => 'patch2',
            'description3' => 'patch3',
        ]));
    }

    public function testItemsAreReordered(): void
    {
        $config = new Config();

        self::assertSame(
            [
                'description1' => 'patch1',
                'description2' => 'patch2',
                'description3' => 'patch3',
            ],
            (new PackagePatches($config, [
                'description3' => 'patch3',
                'description2' => 'patch2',
                'description1' => 'patch1',
            ]))->jsonSerialize()
        );
    }

    public function testIsEmpty(): void
    {
        $config = new Config();

        self::assertTrue((new PackagePatches($config))->isEmpty());
        self::assertFalse((new PackagePatches($config, ['description' => 'patch']))->isEmpty());
    }

    public function testRemove(): void
    {
        $config = new Config();

        self::assertNull((new PackagePatches($config))->remove('invalid-description'));
        self::assertSame('patch', (new PackagePatches($config, [
            'description' => 'patch',
        ]))->remove('description'));

        $packagePatches = new PackagePatches($config, [
            'description3' => 'patch3',
            'description2' => 'patch2',
            'description1' => 'patch1',
        ]);

        self::assertSame('patch2', $packagePatches->remove('description2'));
        self::assertSame(
            [
                'description1' => 'patch1',
                'description3' => 'patch3',
            ],
            $packagePatches->jsonSerialize()
        );

        self::assertSame('patch1', $packagePatches->remove('description1'));
        self::assertSame(
            [
                'description3' => 'patch3',
            ],
            $packagePatches->jsonSerialize()
        );
    }

    public function testGetConfig(): void
    {
        $config = new Config();

        self::assertSame(
            $config,
            (new PackagePatches($config))->getConfig()
        );
    }

    public function testJsonSerialize(): void
    {
        $config = new Config();

        self::assertSame(
            [
                'description1' => 'patch1',
                'description2' => 'patch2',
                'description3' => 'patch3',
            ],
            (new PackagePatches($config, [
                'description3' => 'patch3',
                'description2' => 'patch2',
                'description1' => 'patch1',
            ]))->jsonSerialize()
        );
    }

    public function testJsonUnserialize(): void
    {
        $config = new Config();

        $packagePatches = new PackagePatches($config);

        self::assertSame(
            [
                'description1' => 'patch1',
                'description2' => 'patch2',
                'description3' => 'patch3',
            ],
            $packagePatches->jsonUnserialize([
                'description3' => 'patch3',
                'description2' => 'patch2',
                'description1' => 'patch1',
            ])->jsonSerialize()
        );

        self::assertSame(
            ['description' => 'patch'],
            $packagePatches->jsonUnserialize(['description' => 'patch'])->jsonSerialize()
        );

        self::assertSame(
            $packagePatches,
            $packagePatches->jsonUnserialize(['description' => 'patch'])
        );
    }

    public function testJsonUnserializeThrowsOnInvalidDescriptionType(): void
    {
        $config = new Config();

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Description is not a string (1).');
        (new PackagePatches($config))->jsonUnserialize([1 => 'patch']);
    }

    public function testJsonUnserializeThrowsOnInvalidPatchType(): void
    {
        $config = new Config();

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Patch is not a string (1).');
        (new PackagePatches($config))->jsonUnserialize(['description' => 1]);
    }
}
