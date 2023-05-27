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

use GsTYPO3\CorePatches\Config\Changes;
use GsTYPO3\CorePatches\Config\Changes\Change;
use GsTYPO3\CorePatches\Exception\UnexpectedValueException;
use GsTYPO3\CorePatches\Tests\Unit\TestCase;

/**
 * @covers \GsTYPO3\CorePatches\Config\Changes
 * @uses \GsTYPO3\CorePatches\Config\Changes\Change
 * @uses \GsTYPO3\CorePatches\Config\Packages
 */
final class ChangesTest extends TestCase
{
    public function testItemsAreAddedDuringConstruction(): void
    {
        $change1 = new Change(1);
        $change2 = new Change(2);
        $change3 = new Change(3);

        $changes = new Changes([$change1, $change2, $change3]);
        self::assertCount(3, $changes);
    }

    public function testAdd(): void
    {
        $changes = new Changes();

        self::assertSame(1, $changes->add(1)->getNumber());
        self::assertCount(1, $changes);
    }

    public function testItemsAreReordered(): void
    {
        $change1 = new Change(1);
        $change2 = new Change(2);
        $change3 = new Change(3);

        $changes = new Changes([$change3, $change2, $change1]);

        $changesArray = array_values($changes->jsonSerialize());

        self::assertSame($change1, $changesArray[0]);
        self::assertSame($change2, $changesArray[1]);
        self::assertSame($change3, $changesArray[2]);
    }

    public function testHasAndFind(): void
    {
        $changes = new Changes([new Change(1)]);

        self::assertTrue($changes->has(1));
        self::assertFalse($changes->has(0));
    }

    public function testIsEmpty(): void
    {
        self::assertTrue((new Changes())->isEmpty());
        self::assertFalse((new Changes([new Change(1)]))->isEmpty());
    }

    public function testRemove(): void
    {
        self::assertNull((new Changes())->remove(0));

        $change1 = new Change(1);
        self::assertSame($change1, (new Changes([$change1]))->remove(1));

        $change1 = new Change(1);
        $change2 = new Change(2);
        $change3 = new Change(3);

        $changes = new Changes([$change3, $change2, $change1]);

        self::assertSame($change2, $changes->remove(2));
        $changesArray = array_values($changes->jsonSerialize());
        self::assertSame($change1, $changesArray[0]);
        self::assertSame($change3, $changesArray[1]);

        self::assertSame($change1, $changes->remove(1));
        self::assertSame($change3, array_values($changes->jsonSerialize())[0]);
    }

    public function testJsonSerialize(): void
    {
        $change1 = new Change(11);
        $change2 = new Change(22);
        $change3 = new Change(33);

        self::assertSame(
            [11 => $change1, 22 => $change2, 33 => $change3],
            (new Changes([$change3, $change2, $change1]))->jsonSerialize()
        );
    }

    public function testJsonUnserialize(): void
    {
        $change1 = new Change(11);
        $change2 = new Change(22);
        $change3 = new Change(33);
        $changes = new Changes([$change1, $change2, $change3]);

        $subject = new Changes();

        self::assertEquals(
            $changes,
            $subject->jsonUnserialize([
                33 => $change3->jsonSerialize(),
                22 => $change2->jsonSerialize(),
                11 => $change1->jsonSerialize(),
            ])
        );

        self::assertEquals(
            [11 => $change1],
            $subject->jsonUnserialize([11 => $change1->jsonSerialize()])->jsonSerialize()
        );

        self::assertEquals(
            [11 => $change1],
            $subject->jsonUnserialize([11])->jsonSerialize()
        );
    }

    public function testJsonUnserializeThrowsOnInvalidType(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionMessage('Change is not an array (string).');
        (new Changes())->jsonUnserialize(['invalid-value']);
    }
}
