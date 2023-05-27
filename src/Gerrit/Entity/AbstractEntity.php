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

namespace GsTYPO3\CorePatches\Gerrit\Entity;

use Composer\Json\JsonFile;
use GsTYPO3\CorePatches\Exception\UnexpectedValueException;
use stdClass;
use Throwable;

abstract class AbstractEntity
{
    /**
     * @param  mixed                    $destinationProperty @todo directly declare on parameter with PHP 8.0
     * @throws UnexpectedValueException
     */
    protected static function assignProperty(
        &$destinationProperty,
        object $sourceObject,
        string $sourceProperty,
        bool $optional = false
    ): void {
        if (!property_exists($sourceObject, $sourceProperty)) {
            if ($optional) {
                return;
            }

            throw new UnexpectedValueException(sprintf('Property "%s" does not exist.', $sourceProperty));
        }

        // @phpstan-ignore-next-line because the existence was ensured before
        $destinationProperty = $sourceObject->$sourceProperty;
    }

    /**
     * @throws UnexpectedValueException
     */
    protected static function jsonToObject(string $json): stdClass
    {
        try {
            // Not the best solution regarding performance but the cleanest way as
            // long as JsonFile does not allow to return objects.
            $object = (object)json_decode(
                json_encode(
                    JsonFile::parseJson($json),
                    JSON_THROW_ON_ERROR
                ),
                true,
                512,
                JSON_THROW_ON_ERROR
            );
        } catch (Throwable $throwable) {
            throw new UnexpectedValueException('Invalid JSON data.', 0, $throwable);
        }

        return $object;
    }

    abstract public static function fromJson(string $json): self;
}
