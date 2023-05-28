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
use Iterator;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;

/**
 * @covers \GsTYPO3\CorePatches\Config
 * @uses \GsTYPO3\CorePatches\Config\Changes
 * @uses \GsTYPO3\CorePatches\Config\Changes\Change
 * @uses \GsTYPO3\CorePatches\Config\Packages
 * @uses \GsTYPO3\CorePatches\Config\Patches
 * @uses \GsTYPO3\CorePatches\Config\Patches\PackagePatches
 * @uses \GsTYPO3\CorePatches\Config\PreferredInstall
 */
final class ConfigTest extends TestCase
{
    use ProphecyTrait;

    /**
     * @dataProvider configurationLoadProvider
     * @param array<string, mixed>|null $configuration
     */
    public function testLoadWorksProperly(
        ?array $configuration,
        int $expectedPreferredInstallCount,
        int $expectedPatchesCount,
        int $expectedAppliedChangesCount,
        int $expectedPreferredInstallChangedCount,
        string $expectedPatchDirectory,
        bool $expectedIgnoreBranch,
        bool $expectedDisableTidyPatches,
        bool $expectedForceTidyPatches
    ): void {
        $jsonFileProphecy = $this->prophesize(JsonFile::class);
        $jsonFileProphecy->read()->willReturn($configuration);

        $jsonConfigSourceProphecy = $this->prophesize(JsonConfigSource::class);

        $config = new Config($jsonFileProphecy->reveal(), $jsonConfigSourceProphecy->reveal());

        self::assertSame($config, $config->load());
        self::assertCount($expectedPreferredInstallCount, $config->getPreferredInstall());
        self::assertCount($expectedPatchesCount, $config->getPatches());
        self::assertCount($expectedAppliedChangesCount, $config->getChanges());
        self::assertCount($expectedPreferredInstallChangedCount, $config->getPreferredInstallChanged());
        self::assertSame($expectedPatchDirectory, $config->getPatchDirectory());
        self::assertSame($expectedIgnoreBranch, $config->getIgnoreBranch());
        self::assertSame($expectedDisableTidyPatches, $config->getDisableTidyPatches());
        self::assertSame($expectedForceTidyPatches, $config->getForceTidyPatches());
    }

    /**
     * @return Iterator<string, array{
     *   configuration: array<string, mixed>|null,
     *   expectedPreferredInstallCount: int,
     *   expectedPatchesCount: int,
     *   expectedAppliedChangesCount: int,
     *   expectedPreferredInstallChangedCount: int,
     *   expectedPatchDirectory: string,
     *   expectedIgnoreBranch: bool,
     *   expectedDisableTidyPatches: bool,
     *   expectedForceTidyPatches: bool
     * }>
     */
    public function configurationLoadProvider(): Iterator
    {
        yield 'full configuration' => [
            'configuration' => [
                'config' => [
                    'preferred-install' => [
                        'package1' => 'source',
                        'package2' => 'dist',
                        'package3' => '',
                    ],
                ],
                'extra' => [
                    'patches' => [
                        'vendor/package1' => [
                            'patch1' => 'patch1.patch',
                            'patch2' => 'patch2.patch',
                            'patch3' => 'patch3.patch',
                        ],
                        'vendor/package2' => [
                            'patch4' => 'patch4.patch',
                            'patch5' => 'patch5.patch',
                            'patch6' => 'patch6.patch',
                        ],
                    ],
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
                        'ignore-branch' => true,
                        'disable-tidy-patches' => true,
                        'force-tidy-patches' => true,
                    ],
                ],
            ],
            'expectedPreferredInstallCount' => 3,
            'expectedPatchesCount' => 2,
            'expectedAppliedChangesCount' => 2,
            'expectedPreferredInstallChangedCount' => 2,
            'expectedPatchDirectory' => 'patch-dir',
            'expectedIgnoreBranch' => true,
            'expectedDisableTidyPatches' => true,
            'expectedForceTidyPatches' => true,
        ];
        yield 'empty configuration' => [
            'configuration' => null,
            'expectedPreferredInstallCount' => 0,
            'expectedPatchesCount' => 0,
            'expectedAppliedChangesCount' => 0,
            'expectedPreferredInstallChangedCount' => 0,
            'expectedPatchDirectory' => 'patches',
            'expectedIgnoreBranch' => false,
            'expectedDisableTidyPatches' => false,
            'expectedForceTidyPatches' => false,
        ];
        yield 'empty extra' => [
            'configuration' => ['extra' => []],
            'expectedPreferredInstallCount' => 0,
            'expectedPatchesCount' => 0,
            'expectedAppliedChangesCount' => 0,
            'expectedPreferredInstallChangedCount' => 0,
            'expectedPatchDirectory' => 'patches',
            'expectedIgnoreBranch' => false,
            'expectedDisableTidyPatches' => false,
            'expectedForceTidyPatches' => false,
        ];
    }

    /**
     * @dataProvider configurationSaveProvider
     * @param array<string, mixed>|null $configuration
     * @param array<string, mixed>      $expectedConfiguration
     */
    public function testSaveWorksProperly(
        ?array $configuration,
        array $expectedConfiguration
    ): void {
        $jsonFileProphecy = $this->prophesize(JsonFile::class);
        $jsonFileProphecy->exists()->willReturn(true);
        $jsonFileProphecy->getPath()->willReturn(self::getTestPath() . '/composer.json');
        $jsonFileProphecy->validateSchema(Argument::any(), Argument::any())->willReturn(true);
        $jsonFileProphecy->read()->will(static fn (): ?array => $configuration);
        $jsonFileProphecy->write(Argument::any())->willReturn();

        $jsonConfigSourceProphecy = $this->prophesize(JsonConfigSource::class);
        $jsonConfigSourceProphecy->addProperty(
            Argument::any(),
            Argument::any()
        )->will(
            static function (array $args) use (&$configuration): void {
                $bits = explode('.', $args[0]);
                $last = array_pop($bits);
                $arr = &$configuration;
                foreach ($bits as $bit) {
                    if (!is_array($arr)) {
                        return;
                    }

                    if (!array_key_exists($bit, $arr)) {
                        $arr[$bit] = [];
                    }

                    $arr = &$arr[$bit];
                }

                $arr[$last] = json_decode(json_encode($args[1], JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
            }
        );
        $jsonConfigSourceProphecy->removeProperty(
            Argument::any()
        )->will(
            static function (array $args) use (&$configuration): void {
                $bits = explode('.', $args[0]);
                $last = array_pop($bits);
                $arr = &$configuration;
                foreach ($bits as $bit) {
                    if (!is_array($arr)) {
                        return;
                    }

                    if (!array_key_exists($bit, $arr)) {
                        return;
                    }

                    $arr = &$arr[$bit];
                }

                if (is_array($arr)) {
                    unset($arr[$last]);
                }
            }
        );
        $jsonConfigSourceProphecy->addConfigSetting(
            Argument::type('string'),
            Argument::type('string')
        )->will(
            static function (array $args) use (&$configuration): void {
                $bits = explode('.', $args[0]);
                $last = array_pop($bits);
                $arr = &$configuration['config'];
                foreach ($bits as $bit) {
                    if (!is_array($arr)) {
                        return;
                    }

                    if (!array_key_exists($bit, $arr)) {
                        $arr[$bit] = [];
                    }

                    $arr = &$arr[$bit];
                }

                $arr[$last] = json_decode(json_encode($args[1], JSON_THROW_ON_ERROR), true, 512, JSON_THROW_ON_ERROR);
            }
        );
        $jsonConfigSourceProphecy->removeConfigSetting(
            Argument::type('string')
        )->will(
            static function (array $args) use (&$configuration): void {
                $bits = explode('.', $args[0]);
                $last = array_pop($bits);
                $arr = &$configuration['config'];
                foreach ($bits as $bit) {
                    if (!is_array($arr)) {
                        return;
                    }

                    if (!array_key_exists($bit, $arr)) {
                        return;
                    }

                    $arr = &$arr[$bit];
                }

                if (is_array($arr)) {
                    unset($arr[$last]);
                }
            }
        );

        $config = new Config($jsonFileProphecy->reveal(), $jsonConfigSourceProphecy->reveal());

        self::assertSame($config, $config->load()->save());
        self::assertSame($expectedConfiguration, $configuration);
    }

    /**
     * @return Iterator<string, array{
     *   configuration: array<string, mixed>|null,
     *   expectedConfiguration: array<string, mixed>
     * }>
     */
    public function configurationSaveProvider(): Iterator
    {
        yield 'empty configuration' => [
            'configuration' => [
                'name' => 'test-vendor/test-package',
                'config' => [
                    'sort-packages' => true,
                ],
                'extra' => [
                    'some-key' => 'some-value',
                ],
            ],
            'expectedConfiguration' => [
                'name' => 'test-vendor/test-package',
                'config' => [
                    'sort-packages' => true,
                ],
                'extra' => [
                    'some-key' => 'some-value',
                ],
            ],
        ];
        yield 'simple configuration' => [
            'configuration' => [
                'name' => 'test-vendor/test-package',
                'config' => [
                    'sort-packages' => true,
                ],
                'extra' => [
                    'some-key' => 'some-value',
                    'patches' => [
                        'vendor/package1' => [
                            'patch1' => 'patch1.patch',
                            'patch2' => 'patch2.patch',
                            'patch3' => 'patch3.patch',
                        ],
                        'vendor/package2' => [
                            'patch4' => 'patch4.patch',
                            'patch5' => 'patch5.patch',
                            'patch6' => 'patch6.patch',
                        ],
                    ],
                    'gilbertsoft/typo3-core-patches' => [
                        'applied-changes' => [
                            12345 => [
                                'packages' => ['package1', 'package2'],
                            ],
                            23456 => [
                                'packages' => ['package2', 'package3'],
                            ],
                        ],
                    ],
                ],
            ],
            'expectedConfiguration' => [
                'name' => 'test-vendor/test-package',
                'config' => [
                    'sort-packages' => true,
                ],
                'extra' => [
                    'some-key' => 'some-value',
                    'patches' => [
                        'vendor/package1' => [
                            'patch1' => 'patch1.patch',
                            'patch2' => 'patch2.patch',
                            'patch3' => 'patch3.patch',
                        ],
                        'vendor/package2' => [
                            'patch4' => 'patch4.patch',
                            'patch5' => 'patch5.patch',
                            'patch6' => 'patch6.patch',
                        ],
                    ],
                    'gilbertsoft/typo3-core-patches' => [
                        'applied-changes' => [
                            12345 => [
                                'packages' => ['package1', 'package2'],
                            ],
                            23456 => [
                                'packages' => ['package2', 'package3'],
                            ],
                        ],
                    ],
                ],
            ],
        ];
        yield 'full configuration' => [
            'configuration' => [
                'name' => 'test-vendor/test-package',
                'config' => [
                    'sort-packages' => true,
                    'preferred-install' => [
                        'package1' => 'source',
                        'package2' => 'dist',
                        'package3' => '',
                    ],
                ],
                'extra' => [
                    'some-key' => 'some-value',
                    'patches' => [
                        'vendor/package1' => [
                            'patch1' => 'patch1.patch',
                            'patch2' => 'patch2.patch',
                            'patch3' => 'patch3.patch',
                        ],
                        'vendor/package2' => [
                            'patch4' => 'patch4.patch',
                            'patch5' => 'patch5.patch',
                            'patch6' => 'patch6.patch',
                        ],
                    ],
                    'gilbertsoft/typo3-core-patches' => [
                        'applied-changes' => [
                            12345 => [
                                'revision' => 1,
                                'packages' => ['package1', 'package2'],
                                'tests' => true,
                                'patch-directory' => 'patch1-dir',
                            ],
                            23456 => [
                                'revision' => '2',
                                'packages' => ['package2', 'package3'],
                                'tests' => false,
                                'patch-directory' => '',
                            ],
                        ],
                        'preferred-install-changed' => [
                            0 => 'package1',
                            1 => 'package2',
                            3 => 'package3',
                        ],
                        'patch-directory' => 'patch-dir',
                        'ignore-branch' => true,
                        'disable-tidy-patches' => true,
                        'force-tidy-patches' => true,
                    ],
                ],
            ],
            'expectedConfiguration' => [
                'name' => 'test-vendor/test-package',
                'config' => [
                    'sort-packages' => true,
                    'preferred-install' => [
                        'package1' => 'source',
                        'package2' => 'dist',
                    ],
                ],
                'extra' => [
                    'some-key' => 'some-value',
                    'patches' => [
                        'vendor/package1' => [
                            'patch1' => 'patch1.patch',
                            'patch2' => 'patch2.patch',
                            'patch3' => 'patch3.patch',
                        ],
                        'vendor/package2' => [
                            'patch4' => 'patch4.patch',
                            'patch5' => 'patch5.patch',
                            'patch6' => 'patch6.patch',
                        ],
                    ],
                    'gilbertsoft/typo3-core-patches' => [
                        'applied-changes' => [
                            12345 => [
                                'revision' => 1,
                                'packages' => ['package1', 'package2'],
                                'tests' => true,
                                'patch-directory' => 'patch1-dir',
                            ],
                            23456 => [
                                'packages' => ['package2', 'package3'],
                            ],
                        ],
                        'preferred-install-changed' => [
                            0 => 'package1',
                            1 => 'package2',
                            2 => 'package3',
                        ],
                        'patch-directory' => 'patch-dir',
                        'ignore-branch' => true,
                        'disable-tidy-patches' => true,
                        'force-tidy-patches' => true,
                    ],
                ],
            ],
        ];
        yield 'preferred-install-changed only configuration' => [
            'configuration' => [
                'name' => 'test-vendor/test-package',
                'extra' => [
                    'gilbertsoft/typo3-core-patches' => [
                        'preferred-install-changed' => [
                            0 => 'package1',
                            1 => 'package2',
                            3 => 'package3',
                        ],
                    ],
                ],
            ],
            'expectedConfiguration' => [
                'name' => 'test-vendor/test-package',
                'extra' => [
                    'gilbertsoft/typo3-core-patches' => [
                        'preferred-install-changed' => [
                            0 => 'package1',
                            1 => 'package2',
                            2 => 'package3',
                        ],
                    ],
                ],
            ],
        ];
    }

    public function testGettersAndSetters(): void
    {
        $config = new Config();

        self::assertSame($config->getChanges(), $config->getChanges());

        self::assertSame($config->getPreferredInstallChanged(), $config->getPreferredInstallChanged());

        self::assertSame('patches', $config->getPatchDirectory());
        self::assertSame($config, $config->setPatchDirectory('patch-dir'));
        self::assertSame('patch-dir', $config->getPatchDirectory());

        self::assertSame($config->getPreferredInstall(), $config->getPreferredInstall());

        self::assertSame($config, $config->setIgnoreBranch(true));
        self::assertTrue($config->getIgnoreBranch());

        self::assertSame($config->getPatches(), $config->getPatches());

        self::assertFalse($config->getDisableTidyPatches());
        self::assertSame($config, $config->setDisableTidyPatches(true));
        self::assertTrue($config->getDisableTidyPatches());

        self::assertFalse($config->getForceTidyPatches());
        self::assertSame($config, $config->setForceTidyPatches(true));
        self::assertTrue($config->getForceTidyPatches());
    }
}
