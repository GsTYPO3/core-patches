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

namespace GsTYPO3\CorePatches\Tests\Unit\Utility;

use GsTYPO3\CorePatches\Tests\Unit\TestCase;
use GsTYPO3\CorePatches\Utility\Utils;

/**
 * @covers \GsTYPO3\CorePatches\Utility\Utils
 */
final class UtilsTest extends TestCase
{
    /**
     * @small
     */
    public function testIsCI(): void
    {
        self::assertFalse(Utils::isCI());
        \putenv('GS_CI=1');
        self::assertTrue(Utils::isCI());
    }
}
