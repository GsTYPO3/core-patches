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
use GsTYPO3\CorePatches\Config\Patches;
use GsTYPO3\CorePatches\Config\Patches\PackagePatches;
use GsTYPO3\CorePatches\Exception\UnexpectedValueException;
use GsTYPO3\CorePatches\Tests\Unit\TestCase;

final class PatchesTest extends TestCase
{
    public function testItemsAreAddedDuringConstruction(): void
    {
        $config = new Config();

        $packagePatches1 = new PackagePatches($config, [
            'description3' => 'patch3',
            'description2' => 'patch2',
            'description1' => 'patch1',
        ]);
        $packagePatches2 = new PackagePatches($config, [
            'description3' => 'patch3',
            'description2' => 'patch2',
            'description1' => 'patch1',
        ]);
        $packagePatches3 = new PackagePatches($config, [
            'description3' => 'patch3',
            'description2' => 'patch2',
            'description1' => 'patch1',
        ]);

        $patches = new Patches($config, [
            'package1' => $packagePatches1,
            'package2' => $packagePatches2,
            'package3' => $packagePatches3,
        ]);
        self::assertCount(3, $patches);
    }

    public function testAdd(): void
    {
        $config = new Config();

        $patches = new Patches($config);

        self::assertCount(3, $patches->add('package', [
            'description3' => 'patch3',
            'description2' => 'patch2',
            'description1' => 'patch1',
        ]));
        self::assertCount(1, $patches);
    }

    public function testItemsAreReordered(): void
    {
        $config = new Config();

        $packagePatches1 = new PackagePatches($config, [
            'description3' => 'patch3',
            'description2' => 'patch2',
            'description1' => 'patch1',
        ]);
        $packagePatches2 = new PackagePatches($config, [
            'description3' => 'patch3',
            'description2' => 'patch2',
            'description1' => 'patch1',
        ]);
        $packagePatches3 = new PackagePatches($config, [
            'description3' => 'patch3',
            'description2' => 'patch2',
            'description1' => 'patch1',
        ]);

        $patches = new Patches($config, [
            'package3' => $packagePatches3,
            'package2' => $packagePatches2,
            'package1' => $packagePatches1,
        ]);

        $patchesArray = array_values($patches->jsonSerialize());

        self::assertSame($packagePatches1, $patchesArray[0]);
        self::assertSame($packagePatches2, $patchesArray[1]);
        self::assertSame($packagePatches3, $patchesArray[2]);
    }

    public function testIsEmpty(): void
    {
        $config = new Config();

        self::assertTrue((new Patches($config))->isEmpty());
        self::assertFalse((new Patches(
            $config,
            ['package' => new PackagePatches($config, [
                'description3' => 'patch3',
                'description2' => 'patch2',
                'description1' => 'patch1',
            ])]
        ))->isEmpty());
    }

    public function testRemove(): void
    {
        $config = new Config();

        self::assertNull((new Patches($config))->remove('invalid-package', []));

        $packagePatches1 = new PackagePatches($config, [
            'description3' => 'patch3',
            'description2' => 'patch2',
            'description1' => 'patch1',
        ]);
        self::assertNull((new Patches($config, ['package' => $packagePatches1]))->remove('package', [
            'description3' => 'patch3',
            'description2' => 'patch2',
            'description1' => 'patch1',
        ]));

        $packagePatches1 = new PackagePatches($config, [
            'description3' => 'patch3',
            'description2' => 'patch2',
            'description1' => 'patch1',
        ]);
        $packagePatches2 = new PackagePatches($config, [
            'description3' => 'patch3',
            'description2' => 'patch2',
            'description1' => 'patch1',
        ]);
        $packagePatches3 = new PackagePatches($config, [
            'description3' => 'patch3',
            'description2' => 'patch2',
            'description1' => 'patch1',
        ]);

        $patches = new Patches($config, [
            'package3' => $packagePatches3,
            'package2' => $packagePatches2,
            'package1' => $packagePatches1,
        ]);

        self::assertSame($packagePatches2, $patches->remove('package2', [
            'description2' => 'patch2',
        ]));
    }

    public function testGetConfig(): void
    {
        $config = new Config();

        self::assertSame(
            $config,
            (new Patches($config))->getConfig()
        );
    }

    public function testJsonSerialize(): void
    {
        $config = new Config();

        $packagePatches1 = new PackagePatches($config, [
            'description3' => 'patch3',
            'description2' => 'patch2',
            'description1' => 'patch1',
        ]);
        $packagePatches2 = new PackagePatches($config, [
            'description3' => 'patch3',
            'description2' => 'patch2',
            'description1' => 'patch1',
        ]);
        $packagePatches3 = new PackagePatches($config, [
            'description3' => 'patch3',
            'description2' => 'patch2',
            'description1' => 'patch1',
        ]);

        self::assertSame(
            [
                'package1' => $packagePatches1,
                'package2' => $packagePatches2,
                'package3' => $packagePatches3,
            ],
            (new Patches($config, [
                'package3' => $packagePatches3,
                'package2' => $packagePatches2,
                'package1' => $packagePatches1,
            ]))->jsonSerialize()
        );
    }

    public function testJsonUnserialize(): void
    {
        $config = new Config();

        $packagePatches1 = new PackagePatches($config, [
            'description3' => 'patch3',
            'description2' => 'patch2',
            'description1' => 'patch1',
        ]);
        $packagePatches2 = new PackagePatches($config, [
            'description3' => 'patch3',
            'description2' => 'patch2',
            'description1' => 'patch1',
        ]);
        $packagePatches3 = new PackagePatches($config, [
            'description3' => 'patch3',
            'description2' => 'patch2',
            'description1' => 'patch1',
        ]);
        $patches = new Patches($config, [
            'package3' => $packagePatches3,
            'package2' => $packagePatches2,
            'package1' => $packagePatches1,
        ]);

        $subject = new Patches($config);

        self::assertEquals(
            $patches,
            $subject->jsonUnserialize([
                'package3' => $packagePatches3->jsonSerialize(),
                'package2' => $packagePatches2->jsonSerialize(),
                'package1' => $packagePatches1->jsonSerialize(),
            ])
        );

        self::assertEquals(
            ['package1' => $packagePatches1],
            $subject->jsonUnserialize(['package1' => $packagePatches1->jsonSerialize()])->jsonSerialize()
        );
    }

    public function testJsonUnserializeThrowsOnInvalidPackageType(): void
    {
        $config = new Config();

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Package name is not a string (integer).');
        (new Patches($config))->jsonUnserialize([1 => ['description' => 'patch']]);
    }

    public function testJsonUnserializeThrowsOnInvalidPatchType(): void
    {
        $config = new Config();

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Patches is not an array (string).');
        (new Patches($config))->jsonUnserialize(['package' => 'invalid-patch']);
    }
}
