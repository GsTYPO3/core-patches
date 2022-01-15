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

/**
 * @see https://review.typo3.org/Documentation/rest-api-changes.html#included-in-info
 */
final class IncludedInInfo extends AbstractEntity
{
    /**
     * @var array<int, string>
     */
    public array $branches = [];

    /**
     * @var array<int, string>
     */
    public array $tags = [];

    /**
     * @var array<string, array<int, string>>
     */
    public array $external = [];

    public static function fromJson(string $json): self
    {
        $object = self::jsonToObject($json);
        $self = new self();

        self::assignProperty($self->branches, $object, 'branches');
        self::assignProperty($self->tags, $object, 'tags');
        self::assignProperty($self->external, $object, 'external', true);

        return $self;
    }
}
