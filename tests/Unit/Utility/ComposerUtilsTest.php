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
use Composer\Factory;
use Composer\IO\BufferIO;
use Composer\Package\PackageInterface;
use GsTYPO3\CorePatches\Tests\Unit\TestCase;
use GsTYPO3\CorePatches\Utility\ComposerUtils;
use RuntimeException;

/**
 * @author Elias Häußler <elias@haeussler.dev>
 */
final class ComposerUtilsTest extends TestCase
{
    private string $previousWorkingDir;

    private string $testWorkingDir;

    private BufferIO $bufferIO;

    private Composer $composer;

    private ComposerUtils $composerUtils;

    protected function setUp(): void
    {
        parent::setUp();

        if (($previousWorkingDir = getcwd()) === false) {
            throw new RuntimeException('Unable to determine current directory.', 1_668_787_261);
        }

        $this->previousWorkingDir = $previousWorkingDir;
        $this->testWorkingDir = self::getTestPath();
        chdir($this->testWorkingDir);

        self::createFiles($this->testWorkingDir, [
            'composer.json' => 'FIX:composer.patches.json',
        ]);

        $this->bufferIO = new BufferIO();
        $this->composer = Factory::create($this->bufferIO);
        $this->composerUtils = new ComposerUtils($this->composer, $this->bufferIO);
    }

    protected function tearDown(): void
    {
        chdir($this->previousWorkingDir);

        parent::tearDown();
    }

    public function testVerifyPatchesForPackageCanHandleBranches(): void
    {
        $package = $this->composer->getRepositoryManager()->findPackage('typo3/cms-core', '^11.5');

        self::assertInstanceOf(PackageInterface::class, $package);
        self::assertSame([], $this->composerUtils->verifyPatchesForPackage($package));
    }
}
