<?php

/*
 * This file is part of TYPO3 Core Patches.
 *
 * (c) Gilbertsoft LLC (gilbertsoft.org)
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace GsTYPO3\CorePatches\Tests\Unit\Tester\Constraint;

use PHPUnit\Framework\Constraint\Constraint;

final class CommandIsSuccessful extends Constraint
{
    /**
     * @var array<int, string>
     */
    private const MAPPING = [
        1 => 'Command failed.',
        2 => 'Command was invalid.',
    ];

    /**
     * @inheritDoc
     */
    public function toString(): string
    {
        return 'is successful';
    }

    /**
     * @inheritDoc
     */
    protected function matches($other): bool
    {
        return $other === 0;
    }

    /**
     * @inheritDoc
     */
    protected function failureDescription($other): string
    {
        return 'the command ' . $this->toString();
    }

    /**
     * @inheritDoc
     */
    protected function additionalFailureDescription($other): string
    {
        return self::MAPPING[$other] ?? sprintf('Command returned exit status %d.', is_int($other) ? $other : -1);
    }
}
