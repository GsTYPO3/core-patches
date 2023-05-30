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

use Composer\Composer;
use Composer\IO\BufferIO;
use Composer\Package\BasePackage;
use Composer\Package\Package;
use Composer\Repository\InstalledRepositoryInterface;
use Composer\Repository\RepositoryManager;
use Composer\Semver\Constraint\ConstraintInterface;
use GsTYPO3\CorePatches\Config\Patches;
use GsTYPO3\CorePatches\Config\Patches\PackagePatches;
use GsTYPO3\CorePatches\Exception\InvalidPatchException;
use GsTYPO3\CorePatches\Exception\NoPatchException;
use GsTYPO3\CorePatches\Tests\Unit\TestCaseWithFixtures;
use GsTYPO3\CorePatches\Utility\PatchUtils;
use Iterator;
use Prophecy\Argument;
use RuntimeException;

/**
 * @covers \GsTYPO3\CorePatches\Utility\PatchUtils
 * @uses \GsTYPO3\CorePatches\Config\Patches
 * @uses \GsTYPO3\CorePatches\Config\Patches\PackagePatches
 */
final class PatchUtilsTest extends TestCaseWithFixtures
{
    private string $previousWorkingDir;

    private string $testWorkingDir;

    protected function setUp(): void
    {
        parent::setUp();

        if (($previousWorkingDir = getcwd()) === false) {
            throw new RuntimeException('Unable to determine current directory.', 1_668_787_261);
        }

        $this->previousWorkingDir = $previousWorkingDir;
        $this->testWorkingDir = self::getTestPath();
        chdir($this->testWorkingDir);
    }

    protected function tearDown(): void
    {
        chdir($this->previousWorkingDir);

        parent::tearDown();
    }

    /**
     * @medium
     */
    public function testGetPatchFileName(): void
    {
        $composerProphecy = $this->prophesize(Composer::class);
        $bufferIOProphecy = $this->prophesize(BufferIO::class);

        $patchUtils = new PatchUtils($composerProphecy->reveal(), $bufferIOProphecy->reveal());

        self::assertSame(
            'destination/vendor-package-review-12345.patch',
            $patchUtils->getPatchFileName('destination', 'vendor/package', 12345)
        );
    }

    /**
     * @medium
     */
    public function testPatchIsPartOfChange(): void
    {
        $composerProphecy = $this->prophesize(Composer::class);
        $bufferIOProphecy = $this->prophesize(BufferIO::class);

        $patchUtils = new PatchUtils($composerProphecy->reveal(), $bufferIOProphecy->reveal());

        self::assertTrue($patchUtils->patchIsPartOfChange(
            $patchUtils->getPatchFileName('destination', 'vendor/package', 12345),
            12345
        ));
        self::assertFalse($patchUtils->patchIsPartOfChange('vendor-package.patch', 12345));
    }

    /**
     * @medium
     * @dataProvider createScenariosProvider
     * @param array{
     *   packages: array<string, bool>,
     *   numericId: int,
     *   subject: string,
     *   patch: string,
     *   destination: string,
     *   includeTests: bool
     * } $input
     * @param array{
     *   directories: array<string>,
     *   files: array<string, string|bool>
     * } $expected
     */
    public function testCreateScenarios(array $input, array $expected): void
    {
        $packageProphecy = $this->prophesize(Package::class);

        $installedRepositoryProphecy = $this->prophesize(InstalledRepositoryInterface::class);
        $installedRepositoryProphecy->findPackage(
            Argument::type('string'),
            Argument::type(ConstraintInterface::class)
        )->will(static function (array $args) use ($input, $packageProphecy): ?BasePackage {
            if ($input['packages'][$args[0]] ?? false) {
                return $packageProphecy->reveal();
            }

            return null;
        });

        $repositoryManagerProphecy = $this->prophesize(RepositoryManager::class);
        $repositoryManagerProphecy->getLocalRepository()->willReturn($installedRepositoryProphecy->reveal());

        $composerProphecy = $this->prophesize(Composer::class);
        $composerProphecy->getRepositoryManager()->willReturn($repositoryManagerProphecy->reveal());

        $bufferIOProphecy = $this->prophesize(BufferIO::class);

        $patchUtils = new PatchUtils($composerProphecy->reveal(), $bufferIOProphecy->reveal());
        $patchUtils->create(
            $input['numericId'],
            $input['subject'],
            \is_string($patch = \file_get_contents(self::getFilename($input['patch']))) ? $patch : '',
            $input['destination'],
            $input['includeTests']
        );

        foreach ($expected['directories'] as $directory) {
            self::assertDirectoryExists($directory);
        }

        foreach ($expected['files'] as $file => $content) {
            if (\is_string($content)) {
                self::assertFileEquals(self::getFilename($content), $file);
            } elseif ($content) {
                self::assertFileExists($file);
            } elseif (!$content) {
                self::assertFileDoesNotExist($file);
            }
        }
    }

    /**
     * @return Iterator<string, array{
     *   input: array{
     *     packages: array<string, bool>,
     *     numericId: int,
     *     subject: string,
     *     patch: string,
     *     destination: string,
     *     includeTests: bool
     *   },
     *   expected: array{
     *     directories: array<string>,
     *     files: array<string, string|bool>
     *   }
     * }>
     */
    public function createScenariosProvider(): Iterator
    {
        yield 'Patch without tests' => [
            'input' => [
                'packages' => [
                    'typo3/cms-backend' => \true,
                    'typo3/cms-core' => \true,
                    'typo3/cms-workspaces' => \false,
                ],
                'numericId' => 12345,
                'subject' => 'Test Patch 1',
                'patch' => 'FIX:73021-full.patch',
                'destination' => 'patch-dir',
                'includeTests' => \false,
            ],
            'expected' => [
                'directories' => [
                    'patch-dir',
                ],
                'files' => [
                    'patch-dir/typo3-cms-backend-review-12345.patch' => 'FIX:73021-split-typo3-cms-backend.patch',
                    'patch-dir/typo3-cms-core-review-12345.patch' => 'FIX:73021-split-typo3-cms-core.patch',
                    'patch-dir/typo3-cms-workspaces-review-12345.patch' => \false,
                ],
            ],
        ];
        yield 'Patch with tests' => [
            'input' => [
                'packages' => [
                    'typo3/cms-backend' => \true,
                    'typo3/cms-core' => \true,
                    'typo3/cms-workspaces' => \false,
                ],
                'numericId' => 12345,
                'subject' => 'Test Patch 1',
                'patch' => 'FIX:73021-full.patch',
                'destination' => 'patch-dir',
                'includeTests' => \true,
            ],
            'expected' => [
                'directories' => [
                    'patch-dir',
                ],
                'files' => [
                    'patch-dir/typo3-cms-backend-review-12345.patch' => 'FIX:73021-split-typo3-cms-backend.patch',
                    'patch-dir/typo3-cms-core-review-12345.patch' => 'FIX:73021-split-typo3-cms-core-tests.patch',
                    'patch-dir/typo3-cms-workspaces-review-12345.patch' => \false,
                ],
            ],
        ];
        yield 'Patch with workspaces' => [
            'input' => [
                'packages' => [
                    'typo3/cms-backend' => \true,
                    'typo3/cms-core' => \true,
                    'typo3/cms-workspaces' => \true,
                ],
                'numericId' => 12345,
                'subject' => 'Test Patch 1',
                'patch' => 'FIX:73021-full.patch',
                'destination' => 'patch-dir',
                'includeTests' => \true,
            ],
            'expected' => [
                'directories' => [
                    'patch-dir',
                ],
                'files' => [
                    'patch-dir/typo3-cms-backend-review-12345.patch' => 'FIX:73021-split-typo3-cms-backend.patch',
                    'patch-dir/typo3-cms-core-review-12345.patch' => 'FIX:73021-split-typo3-cms-core-tests.patch',
                    'patch-dir/typo3-cms-workspaces-review-12345.patch' => 'FIX:73021-split-typo3-cms-workspaces.patch',
                ],
            ],
        ];
    }

    /**
     * @medium
     */
    public function testSplitThrowsOnInvalidPatch(): void
    {
        $composerProphecy = $this->prophesize(Composer::class);
        $bufferIOProphecy = $this->prophesize(BufferIO::class);

        $patchUtils = new PatchUtils($composerProphecy->reveal(), $bufferIOProphecy->reveal());

        $this->expectException(InvalidPatchException::class);

        $patchUtils->split('', false);
    }

    /**
     * @medium
     */
    public function testSaveThrowsOnEmptyPatch(): void
    {
        $composerProphecy = $this->prophesize(Composer::class);
        $bufferIOProphecy = $this->prophesize(BufferIO::class);

        $patchUtils = new PatchUtils($composerProphecy->reveal(), $bufferIOProphecy->reveal());

        $this->expectException(NoPatchException::class);

        $patchUtils->save('', 0, '', []);
    }

    /**
     * @medium
     */
    public function testRemove(): void
    {
        $composerProphecy = $this->prophesize(Composer::class);
        $bufferIOProphecy = $this->prophesize(BufferIO::class);

        $patchUtils = new PatchUtils($composerProphecy->reveal(), $bufferIOProphecy->reveal());

        self::createFiles($this->testWorkingDir, [
            'typo3-cms-core-review-73021.patch' => 'FIX:73021-split-typo3-cms-core-tests.patch',
        ]);

        $patches = new Patches([
            'typo3/cms-core' => new PackagePatches(['Patch 1' => 'typo3-cms-core-review-73021.patch']),
        ]);

        $patchesRemoved = $patchUtils->remove([73021], $patches);
        self::assertEquals($patches, $patchesRemoved);
        self::assertFileDoesNotExist('typo3-cms-core-review-73021.patch');
    }

    /**
     * @medium
     */
    public function testTruncate(): void
    {
        $composerProphecy = $this->prophesize(Composer::class);
        $bufferIOProphecy = $this->prophesize(BufferIO::class);

        $patchUtils = new PatchUtils($composerProphecy->reveal(), $bufferIOProphecy->reveal());

        self::createFiles($this->testWorkingDir, [
            'typo3-cms-core-review-73021.patch' => 'FIX:73021-split-typo3-cms-core-tests.patch',
        ]);

        $patches = new Patches([
            'typo3/cms-core' => new PackagePatches(['Patch 1' => 'typo3-cms-core-review-73021.patch']),
        ]);

        $patchesPrepared = $patchUtils->truncate([73021], $patches);

        self::assertEquals($patches, $patchesPrepared);
        self::assertStringEqualsFile('typo3-cms-core-review-73021.patch', '');
    }
}
