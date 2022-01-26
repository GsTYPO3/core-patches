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

use Composer\Config\JsonConfigSource;
use Composer\Json\JsonFile;
use GsTYPO3\CorePatches\Config;
use Prophecy\Argument;

final class ConfigTest extends TestCase
{
    public function testLoadWorksProperly(): void
    {
        $jsonFile = $this->prophesize(JsonFile::class);
        $jsonFile->read()->willReturn([
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

        $config->load($jsonFile->reveal());

        self::assertCount(2, $config->getChanges());
        self::assertCount(2, $config->getPreferredInstallChanged());
        self::assertSame('patch-dir', $config->getPatchDirectory());

        $jsonFile->read()->willReturn([
            'extra' => [],
        ]);

        self::assertSame($config, $config->load($jsonFile->reveal()));

        self::assertCount(0, $config->getChanges());
        self::assertCount(0, $config->getPreferredInstallChanged());
    }

    public function testSaveWorksProperly(): void
    {
        $jsonConfigSource = $this->prophesize(JsonConfigSource::class);

        $jsonConfigSource->addProperty(
            Argument::exact('extra.gilbertsoft/typo3-core-patches.applied-changes'),
            Argument::type('array')
        )->shouldBeCalledTimes(2);

        $jsonConfigSource->addProperty(
            Argument::exact('extra.gilbertsoft/typo3-core-patches.preferred-install-changed'),
            Argument::type('array')
        )->shouldBeCalledTimes(2);

        $jsonConfigSource->addProperty(
            Argument::exact('extra.gilbertsoft/typo3-core-patches.patch-directory'),
            Argument::type('string')
        )->shouldBeCalledTimes(2);

        $jsonConfigSource->removeProperty(
            Argument::exact('extra.gilbertsoft/typo3-core-patches.applied-changes')
        )->shouldBeCalledTimes(2);

        $jsonConfigSource->removeProperty(
            Argument::exact('extra.gilbertsoft/typo3-core-patches.preferred-install-changed')
        )->shouldBeCalledTimes(2);

        $jsonConfigSource->removeProperty(
            Argument::exact('extra.gilbertsoft/typo3-core-patches.patch-directory')
        )->shouldBeCalledTimes(2);

        $jsonConfigSource->removeProperty(
            Argument::exact('extra.gilbertsoft/typo3-core-patches')
        )->shouldBeCalledOnce();

        $config = new Config();
        $config->getChanges()->add(1);
        $config->getPreferredInstallChanged()->add('package');
        self::assertSame($config, $config->setPatchDirectory('patch-dir'));

        self::assertSame($config, $config->save($jsonConfigSource->reveal()));

        $config = new Config();
        $config->getChanges()->add(1);

        self::assertSame($config, $config->save($jsonConfigSource->reveal()));

        $config = new Config();
        $config->getPreferredInstallChanged()->add('package');

        self::assertSame($config, $config->save($jsonConfigSource->reveal()));

        $config = new Config();
        self::assertSame($config, $config->setPatchDirectory('patch-dir'));

        self::assertSame($config, $config->save($jsonConfigSource->reveal()));

        $config = new Config();

        self::assertSame($config, $config->save($jsonConfigSource->reveal()));
    }
}
