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
 * A minimal implementation of the required properties.
 *
 * @see https://review.typo3.org/Documentation/rest-api-changes.html#change-info
 */
final class ChangeInfo extends AbstractEntity
{
    public string $id = '';

    public string $branch = '';

    public string $changeId = '';

    public string $subject = '';

    public int $number = 0;

    public static function fromJson(string $json): self
    {
        $object = self::jsonToObject($json);
        $self = new self();

        self::assignProperty($self->id, $object, 'id');
        self::assignProperty($self->branch, $object, 'branch');
        self::assignProperty($self->changeId, $object, 'change_id');
        self::assignProperty($self->subject, $object, 'subject');
        self::assignProperty($self->number, $object, '_number');

        return $self;
    }
}
