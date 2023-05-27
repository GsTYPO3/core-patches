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
use Composer\Console\Application;
use Composer\Factory;
use Composer\IO\BufferIO;
use Composer\Package\PackageInterface;
use GsTYPO3\CorePatches\Config;
use GsTYPO3\CorePatches\Tests\Unit\TestCase;
use GsTYPO3\CorePatches\Utility\ComposerUtils;
use RuntimeException;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Output\BufferedOutput;

/**
 * @covers \GsTYPO3\CorePatches\Utility\ComposerUtils
 * @uses \GsTYPO3\CorePatches\CommandProvider
 * @uses \GsTYPO3\CorePatches\Command\Typo3\Patch\ApplyCommand
 * @uses \GsTYPO3\CorePatches\Command\Typo3\Patch\RemoveCommand
 * @uses \GsTYPO3\CorePatches\Command\Typo3\Patch\UpdateCommand
 * @uses \GsTYPO3\CorePatches\Config
 * @uses \GsTYPO3\CorePatches\Config\Changes
 * @uses \GsTYPO3\CorePatches\Config\Changes\Change
 * @uses \GsTYPO3\CorePatches\Config\Packages
 * @uses \GsTYPO3\CorePatches\Config\Patches
 * @uses \GsTYPO3\CorePatches\Config\Patches\PackagePatches
 * @uses \GsTYPO3\CorePatches\Config\PreferredInstall
 * @uses \GsTYPO3\CorePatches\Gerrit\Entity\AbstractEntity
 * @uses \GsTYPO3\CorePatches\Gerrit\Entity\ChangeInfo
 * @uses \GsTYPO3\CorePatches\Gerrit\Entity\IncludedInInfo
 * @uses \GsTYPO3\CorePatches\Gerrit\RestApi
 * @uses \GsTYPO3\CorePatches\Utility\PatchUtils
 * @uses \GsTYPO3\CorePatches\Utility\Utils
 */
final class ComposerUtilsTest extends TestCase
{
    private Application $application;

    private string $previousWorkingDir;

    private string $testWorkingDir;

    private BufferedOutput $bufferedOutput;

    private BufferIO $bufferIO;

    private Composer $composer;

    private ComposerUtils $composerUtils;

    private Config $config;

    protected function setUp(): void
    {
        parent::setUp();

        $this->application = new Application();
        $this->application->setAutoExit(false);

        if (($previousWorkingDir = getcwd()) === false) {
            throw new RuntimeException('Unable to determine current directory.', 1_668_787_261);
        }

        $this->previousWorkingDir = $previousWorkingDir;
        $this->testWorkingDir = self::getTestPath();
        chdir($this->testWorkingDir);

        self::createFiles($this->testWorkingDir, [
            'composer.json' => 'FIX:composer.json',
        ]);

        $this->bufferedOutput = new BufferedOutput();
        $this->application->run(
            new ArrayInput([
                'command' => 'install',
                '--no-dev' => true,
                '--no-progress' => true,
                '--ansi' => true,
                '--no-interaction' => true,
            ]),
            $this->bufferedOutput
        );

        $this->bufferIO = new BufferIO();
        $this->composer = Factory::create($this->bufferIO);
        $this->composerUtils = new ComposerUtils($this->composer, $this->bufferIO);
        $this->config = new Config();
    }

    protected function tearDown(): void
    {
        chdir($this->previousWorkingDir);

        parent::tearDown();
    }

    /**
     * @long
     */
    public function testAddPatchesAddsPatchWithoutTests(): void
    {
        self::assertSame(
            3,
            $this->composerUtils->addPatches(['72954', '73021'], 'patch-dir', false),
            $this->bufferIO->getOutput()
        );
    }

    /**
     * @long
     */
    public function testAddPatchesAddsPatchWithTests(): void
    {
        self::assertSame(
            3,
            $this->composerUtils->addPatches(['72954', '73021'], 'patch-dir', true),
            $this->bufferIO->getOutput()
        );
    }

    /**
     * @long
     */
    public function testUpdatePatches(): void
    {
        self::assertSame(
            3,
            $this->composerUtils->addPatches(['72954', '73021'], 'patch-dir', false),
            $this->bufferIO->getOutput()
        );
        self::assertSame(
            3,
            $this->composerUtils->updatePatches(['72954', '73021']),
            $this->bufferIO->getOutput()
        );
    }

    /**
     * @long
     */
    public function testVerifyPatchesForPackageCanHandleBranches(): void
    {
        self::assertSame(
            3,
            $this->composerUtils->addPatches(['72954', '73021'], 'patch-dir', true),
            $this->bufferIO->getOutput()
        );

        $package = $this->composer->getRepositoryManager()->findPackage('typo3/cms-core', '^11.5');

        self::assertInstanceOf(PackageInterface::class, $package);
        self::assertSame(
            [],
            $this->composerUtils->verifyPatchesForPackage($package),
            $this->bufferIO->getOutput()
        );
    }

    /**
     * @long
     */
    public function testRemovePatches(): void
    {
        self::assertSame(
            3,
            $this->composerUtils->addPatches(['72954', '73021'], 'patch-dir', true),
            $this->bufferIO->getOutput()
        );
        $changes = $this->config->load()->getChanges()->jsonSerialize();
        self::assertArrayHasKey(72954, $changes, \var_export($changes, \true));
        self::assertArrayHasKey(73021, $changes, \var_export($changes, \true));
        self::assertSame(
            1,
            $this->composerUtils->removePatches(['72954']),
            $this->bufferIO->getOutput()
        );
        $changes = $this->config->load()->getChanges()->jsonSerialize();
        self::assertArrayNotHasKey(72954, $changes, \var_export($changes, \true));
        self::assertArrayHasKey(73021, $changes, \var_export($changes, \true));
        self::assertSame(
            2,
            $this->composerUtils->removePatches(['73021']),
            $this->bufferIO->getOutput()
        );
        $changes = $this->config->load()->getChanges()->jsonSerialize();
        self::assertArrayNotHasKey(72954, $changes, \var_export($changes, \true));
        self::assertArrayNotHasKey(73021, $changes, \var_export($changes, \true));
    }

    public function testUpdateLock(): void
    {
        $this->bufferedOutput->fetch();
        $this->composerUtils->updateLock($this->bufferedOutput);
        self::assertStringContainsString('Nothing to modify in lock file', $this->bufferedOutput->fetch());
    }
}
