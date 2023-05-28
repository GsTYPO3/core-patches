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
use GsTYPO3\CorePatches\Tests\Unit\TestCase;
use GsTYPO3\CorePatches\Utility\PatchUtils;
use RuntimeException;

/**
 * @covers \GsTYPO3\CorePatches\Utility\PatchUtils
 */
final class PatchUtilsTest extends TestCase
{
    private string $previousWorkingDir;

    private string $testWorkingDir;

    private BufferIO $bufferIO;

    private Composer $composer;

    private PatchUtils $patchUtils;

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
            'composer.json' => 'FIX:composer.json',
        ]);

        $this->bufferIO = new BufferIO();
        $this->composer = Factory::create($this->bufferIO);

        $this->patchUtils = new PatchUtils($this->composer, $this->bufferIO);
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
        self::assertSame(
            'destination/vendor-package-review-12345.patch',
            $this->patchUtils->getPatchFileName('destination', 'vendor/package', 12345)
        );
    }

    /**
     * @medium
     */
    public function testPatchIsPartOfChange(): void
    {
        self::assertTrue($this->patchUtils->patchIsPartOfChange(
            $this->patchUtils->getPatchFileName('destination', 'vendor/package', 12345),
            12345
        ));
        self::assertFalse($this->patchUtils->patchIsPartOfChange('vendor-package.patch', 12345));
    }
}
