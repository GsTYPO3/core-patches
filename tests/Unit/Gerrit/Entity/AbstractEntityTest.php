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

namespace GsTYPO3\CorePatches\Tests\Unit\Gerrit\Entity;

use GsTYPO3\CorePatches\Tests\Unit\TestCase;
use stdClass;
use UnexpectedValueException;

/**
 * @covers \GsTYPO3\CorePatches\Gerrit\Entity\AbstractEntity
 */
final class AbstractEntityTest extends TestCase
{
    public function testAssignProperty(): void
    {
        $object = new stdClass();
        $object->propertyName = 'value';

        TestEntity::testAssignProperty(
            $property,
            $object,
            'propertyName',
            false
        );

        self::assertSame('value', $property);
    }

    public function testAssignPropertyWorksOnOptionalProperty(): void
    {
        $object = new stdClass();

        TestEntity::testAssignProperty(
            $property,
            $object,
            'propertyName',
            true
        );

        self::assertNull($property);
    }

    public function testAssignPropertyThrowsOnMissingProperty(): void
    {
        $object = new stdClass();

        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionCode(0);
        $this->expectExceptionMessage('Property "propertyName" does not exist.');

        TestEntity::testAssignProperty(
            $property,
            $object,
            'propertyName',
            false
        );
    }

    public function testFromJson(): void
    {
        $testEntity = TestEntity::fromJson('{"testProperty": "propertyValue"}');

        self::assertSame('propertyValue', $testEntity->testProperty);
    }

    public function testFromJsonThrowsOnInvalidJson(): void
    {
        $this->expectException(UnexpectedValueException::class);
        $this->expectExceptionCode(0);
        $this->expectExceptionMessage('Invalid JSON data.');

        TestEntity::fromJson('');
    }
}
