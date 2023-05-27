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

use GsTYPO3\CorePatches\Config\Patches\PackagePatches;
use GsTYPO3\CorePatches\Exception\UnexpectedValueException;
use GsTYPO3\CorePatches\Tests\Unit\TestCase;

/**
 * @covers \GsTYPO3\CorePatches\Config\Patches\PackagePatches
 */
final class PackagePatchesTest extends TestCase
{
    public function testItemsAreAddedDuringConstruction(): void
    {
        self::assertCount(3, new PackagePatches([
            'description1' => 'patch1',
            'description2' => 'patch2',
            'description3' => 'patch3',
        ]));
    }

    public function testItemsAreReordered(): void
    {
        self::assertSame(
            [
                'description1' => 'patch1',
                'description2' => 'patch2',
                'description3' => 'patch3',
            ],
            (new PackagePatches([
                'description3' => 'patch3',
                'description2' => 'patch2',
                'description1' => 'patch1',
            ]))->jsonSerialize()
        );
    }

    public function testIsEmpty(): void
    {
        self::assertTrue((new PackagePatches())->isEmpty());
        self::assertFalse((new PackagePatches(['description' => 'patch']))->isEmpty());
    }

    public function testRemove(): void
    {
        self::assertNull((new PackagePatches())->remove('invalid-description'));
        self::assertSame('patch', (new PackagePatches([
            'description' => 'patch',
        ]))->remove('description'));

        $packagePatches = new PackagePatches([
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

    public function testJsonSerialize(): void
    {
        self::assertSame(
            [
                'description1' => 'patch1',
                'description2' => 'patch2',
                'description3' => 'patch3',
            ],
            (new PackagePatches([
                'description3' => 'patch3',
                'description2' => 'patch2',
                'description1' => 'patch1',
            ]))->jsonSerialize()
        );
    }

    public function testJsonUnserialize(): void
    {
        $packagePatches = new PackagePatches();

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
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Description is not a string (1).');
        (new PackagePatches())->jsonUnserialize([1 => 'patch']);
    }

    public function testJsonUnserializeThrowsOnInvalidPatchType(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Patch is not a string (1).');
        (new PackagePatches())->jsonUnserialize(['description' => 1]);
    }

    public function testIterator(): void
    {
        $packagePatches = new PackagePatches([
            'description3' => 'patch3',
            'description2' => 'patch2',
            'description1' => 'patch1',
        ]);

        $patches = [];
        foreach ($packagePatches as $description => $patch) {
            $patches[$description] = $patch;
        }

        self::assertSame(
            [
                'description1' => 'patch1',
                'description2' => 'patch2',
                'description3' => 'patch3',
            ],
            $patches
        );
    }
}
