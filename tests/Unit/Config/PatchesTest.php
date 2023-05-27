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

use GsTYPO3\CorePatches\Config\Patches;
use GsTYPO3\CorePatches\Config\Patches\PackagePatches;
use GsTYPO3\CorePatches\Exception\UnexpectedValueException;
use GsTYPO3\CorePatches\Tests\Unit\TestCase;

/**
 * @covers \GsTYPO3\CorePatches\Config\Patches
 * @uses \GsTYPO3\CorePatches\Config\Patches\PackagePatches
 */
final class PatchesTest extends TestCase
{
    public function testItemsAreAddedDuringConstruction(): void
    {
        $packagePatches1 = new PackagePatches([
            'description3' => 'patch3',
            'description2' => 'patch2',
            'description1' => 'patch1',
        ]);
        $packagePatches2 = new PackagePatches([
            'description3' => 'patch3',
            'description2' => 'patch2',
            'description1' => 'patch1',
        ]);
        $packagePatches3 = new PackagePatches([
            'description3' => 'patch3',
            'description2' => 'patch2',
            'description1' => 'patch1',
        ]);

        $patches = new Patches([
            'package1' => $packagePatches1,
            'package2' => $packagePatches2,
            'package3' => $packagePatches3,
        ]);
        self::assertCount(3, $patches);
    }

    public function testAdd(): void
    {
        $patches = new Patches();

        self::assertCount(3, $patches->add('package1', [
            'description3' => 'patch3',
            'description2' => 'patch2',
            'description1' => 'patch1',
        ]));
        self::assertCount(1, $patches->add('package1', [
            'description4' => 'patch4',
        ]));
        self::assertCount(1, $patches);
    }

    public function testItemsAreReordered(): void
    {
        $packagePatches1 = new PackagePatches([
            'description3' => 'patch3',
            'description2' => 'patch2',
            'description1' => 'patch1',
        ]);
        $packagePatches2 = new PackagePatches([
            'description3' => 'patch3',
            'description2' => 'patch2',
            'description1' => 'patch1',
        ]);
        $packagePatches3 = new PackagePatches([
            'description3' => 'patch3',
            'description2' => 'patch2',
            'description1' => 'patch1',
        ]);

        $patches = new Patches([
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
        self::assertTrue((new Patches())->isEmpty());
        self::assertFalse((new Patches(
            ['package' => new PackagePatches([
                'description3' => 'patch3',
                'description2' => 'patch2',
                'description1' => 'patch1',
            ])]
        ))->isEmpty());
    }

    public function testRemove(): void
    {
        self::assertNull((new Patches())->remove('invalid-package', []));

        $packagePatches1 = new PackagePatches([
            'description3' => 'patch3',
            'description2' => 'patch2',
            'description1' => 'patch1',
        ]);
        self::assertNull((new Patches(['package' => $packagePatches1]))->remove('package', [
            'description3' => 'patch3',
            'description2' => 'patch2',
            'description1' => 'patch1',
        ]));

        $packagePatches1 = new PackagePatches([
            'description3' => 'patch3',
            'description2' => 'patch2',
            'description1' => 'patch1',
        ]);
        $packagePatches2 = new PackagePatches([
            'description3' => 'patch3',
            'description2' => 'patch2',
            'description1' => 'patch1',
        ]);
        $packagePatches3 = new PackagePatches([
            'description3' => 'patch3',
            'description2' => 'patch2',
            'description1' => 'patch1',
        ]);

        $patches = new Patches([
            'package3' => $packagePatches3,
            'package2' => $packagePatches2,
            'package1' => $packagePatches1,
        ]);

        self::assertSame($packagePatches2, $patches->remove('package2', [
            'description2' => 'patch2',
        ]));
    }

    public function testJsonSerialize(): void
    {
        $packagePatches1 = new PackagePatches([
            'description3' => 'patch3',
            'description2' => 'patch2',
            'description1' => 'patch1',
        ]);
        $packagePatches2 = new PackagePatches([
            'description3' => 'patch3',
            'description2' => 'patch2',
            'description1' => 'patch1',
        ]);
        $packagePatches3 = new PackagePatches([
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
            (new Patches([
                'package3' => $packagePatches3,
                'package2' => $packagePatches2,
                'package1' => $packagePatches1,
            ]))->jsonSerialize()
        );
    }

    public function testJsonUnserialize(): void
    {
        $packagePatches1 = new PackagePatches([
            'description3' => 'patch3',
            'description2' => 'patch2',
            'description1' => 'patch1',
        ]);
        $packagePatches2 = new PackagePatches([
            'description3' => 'patch3',
            'description2' => 'patch2',
            'description1' => 'patch1',
        ]);
        $packagePatches3 = new PackagePatches([
            'description3' => 'patch3',
            'description2' => 'patch2',
            'description1' => 'patch1',
        ]);
        $patches = new Patches([
            'package3' => $packagePatches3,
            'package2' => $packagePatches2,
            'package1' => $packagePatches1,
        ]);

        $subject = new Patches();

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
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Package name is not a string (integer).');
        (new Patches())->jsonUnserialize([1 => ['description' => 'patch']]);
    }

    public function testJsonUnserializeThrowsOnInvalidPatchType(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Patches is not an array (string).');
        (new Patches())->jsonUnserialize(['package' => 'invalid-patch']);
    }

    public function testArrayAccess(): void
    {
        $patches = new Patches();
        $package = $patches->add('package1', [
            'description1' => 'patch1',
        ]);

        self::assertFalse($patches->offsetExists('dummy'));
        self::assertTrue($patches->offsetExists('package1'));
        self::assertSame($package, $patches->offsetGet('package1'));

        $patches->offsetSet('package2', new PackagePatches());
        self::assertCount(2, $patches);
        self::assertFalse($patches->offsetExists('dummy'));

        $patches->offsetUnset('package2');
        self::assertCount(1, $patches);
        self::assertFalse($patches->offsetExists('package2'));
    }
}
