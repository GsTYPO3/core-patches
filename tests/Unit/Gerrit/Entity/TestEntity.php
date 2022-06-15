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

use GsTYPO3\CorePatches\Gerrit\Entity\AbstractEntity;

final class TestEntity extends AbstractEntity
{
    public string $testProperty = '';

    /**
     * @param mixed $destinationProperty @todo directly declare on parameter with PHP 8.0
     */
    public static function testAssignProperty(
        &$destinationProperty,
        object $sourceObject,
        string $sourceProperty,
        bool $optional = false
    ): void {
        self::assignProperty($destinationProperty, $sourceObject, $sourceProperty, $optional);
    }

    public static function fromJson(string $json): self
    {
        $object = self::jsonToObject($json);
        $self = new self();

        self::assignProperty($self->testProperty, $object, 'testProperty');

        return $self;
    }
}
