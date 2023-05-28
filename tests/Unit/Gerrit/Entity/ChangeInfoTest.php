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

use GsTYPO3\CorePatches\Gerrit\Entity\ChangeInfo;
use GsTYPO3\CorePatches\Tests\Unit\TestCase;

/**
 * @covers \GsTYPO3\CorePatches\Gerrit\Entity\ChangeInfo
 * @uses \GsTYPO3\CorePatches\Gerrit\Entity\AbstractEntity
 */
final class ChangeInfoTest extends TestCase
{
    public function testFromJson(): void
    {
        $changeInfo = ChangeInfo::fromJson('{
            "id": "idValue",
            "branch": "branchValue",
            "change_id": "changeIdValue",
            "subject": "subjectValue",
            "_number": 12345
        }');

        self::assertSame('idValue', $changeInfo->id);
        self::assertSame('branchValue', $changeInfo->branch);
        self::assertSame('changeIdValue', $changeInfo->changeId);
        self::assertSame('subjectValue', $changeInfo->subject);
        self::assertSame(12345, $changeInfo->number);
    }
}
