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

namespace GsTYPO3\CorePatches\Config;

use JsonSerializable;

interface PersistenceInterface extends JsonSerializable
{
    /**
     * Initializes the object from the JSON array, which was returned from
     * json_decode.
     *
     * @param mixed[] $json
     */
    public function jsonUnserialize(array $json): self;
}
