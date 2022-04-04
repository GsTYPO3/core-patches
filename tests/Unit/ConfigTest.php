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

namespace GsTYPO3\CorePatches\Tests\Unit;

use Composer\Json\JsonFile;
use GsTYPO3\CorePatches\Config;

final class ConfigTest extends TestCase
{
    public function testLoadWorksProperly(): void
    {
        $objectProphecy = $this->prophesize(JsonFile::class);
        $objectProphecy->read()->willReturn([
            'extra' => [
                'gilbertsoft/typo3-core-patches' => [
                    'applied-changes' => [
                        [
                            'number' => 1,
                            'revision' => -1,
                            'packages' => ['package1'],
                            'tests' => true,
                        ],
                        [
                            'number' => 2,
                            'revision' => -1,
                            'packages' => ['package2'],
                            'tests' => true,
                        ],
                    ],
                    'preferred-install-changed' => ['package1', 'package2'],
                    'patch-directory' => 'patch-dir',
                ],
            ],
        ]);

        $config = new Config();

        $config->load($objectProphecy->reveal());

        self::assertCount(2, $config->getChanges());
        self::assertCount(2, $config->getPreferredInstallChanged());
        self::assertSame('patch-dir', $config->getPatchDirectory());

        $objectProphecy->read()->willReturn([
            'extra' => [],
        ]);

        self::assertSame($config, $config->load($objectProphecy->reveal()));

        self::assertCount(0, $config->getChanges());
        self::assertCount(0, $config->getPreferredInstallChanged());
    }

    public function testSaveWorksProperly(): void
    {
        // @todo rewrite and check written result

        $config = new Config();
        $config->getChanges()->add(1);
        $config->getPreferredInstallChanged()->add('package');
        self::assertSame($config, $config->setPatchDirectory('patch-dir'));

        self::assertSame($config, $config->save());

        $config = new Config();
        $config->getChanges()->add(1);

        self::assertSame($config, $config->save());

        $config = new Config();
        $config->getPreferredInstallChanged()->add('package');

        self::assertSame($config, $config->save());

        $config = new Config();
        self::assertSame($config, $config->setPatchDirectory('patch-dir'));

        self::assertSame($config, $config->save());

        $config = new Config();

        self::assertSame($config, $config->save());
    }
}
