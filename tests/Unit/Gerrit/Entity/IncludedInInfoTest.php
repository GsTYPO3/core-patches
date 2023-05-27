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

use GsTYPO3\CorePatches\Gerrit\Entity\IncludedInInfo;
use GsTYPO3\CorePatches\Tests\Unit\TestCase;

/**
 * @covers \GsTYPO3\CorePatches\Gerrit\Entity\IncludedInInfo
 * @uses \GsTYPO3\CorePatches\Gerrit\Entity\AbstractEntity
 */
final class IncludedInInfoTest extends TestCase
{
    public function testFromJson(): void
    {
        $includedInInfo = IncludedInInfo::fromJson('{
            "branches": ["branch1", "branch2"],
            "tags": ["tags1", "tag2"],
            "external": {
                "external1": ["target1", "target2"],
                "external2": ["target1", "target2"]
            }
        }');

        self::assertSame(['branch1', 'branch2'], $includedInInfo->branches);
        self::assertSame(['tags1', 'tag2'], $includedInInfo->tags);
        self::assertSame([
            'external1' => ['target1', 'target2'],
            'external2' => ['target1', 'target2'],
        ], $includedInInfo->external);
    }
}
