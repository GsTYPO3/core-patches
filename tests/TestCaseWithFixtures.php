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

namespace GsTYPO3\CorePatches\Tests;

use RuntimeException;
use Symfony\Component\Filesystem\Filesystem;

abstract class TestCaseWithFixtures extends TestCase
{
    private static string $rootPath;

    private static string $fixturePath;

    private static string $testPath;

    private static string $templatePath;

    private static Filesystem $filesystem;

    public static function setUpBeforeClass(): void
    {
        self::$rootPath = dirname(__DIR__, 1);
        self::$fixturePath = __DIR__ . '/Fixtures';
        self::$testPath = self::$rootPath . '/var/tests';
        self::$templatePath = self::$rootPath . '/templates';

        self::$filesystem = new Filesystem();
        self::$filesystem->mkdir(self::$testPath);
    }

    protected function setUp(): void
    {
    }

    protected function tearDown(): void
    {
        /*
        if (!$this->hasFailed() && self::$filesystem->exists(self::$testPath)) {
            self::$filesystem->remove(self::$testPath);
        }
        */
    }

    protected static function getRootPath(): string
    {
        return self::$rootPath;
    }

    protected static function getFilename(string $filename): string
    {
        [$prefix, $filename] = explode(':', $filename, 2);

        switch ($prefix) {
            case 'TPL':
                return self::getTemplateFilename($filename);

            case 'FIX':
                return self::getFixtureFilename($filename);

            default:
                throw new RuntimeException(sprintf('Invalid prefix (%s).', $prefix), 1_636_451_407);
        }
    }

    protected static function getFixturePath(): string
    {
        return self::$fixturePath;
    }

    protected static function getFixtureFilename(string $filename): string
    {
        return self::$fixturePath . '/' . $filename;
    }

    protected static function getTestPath(?string $subFolder = null): string
    {
        $fs = self::getFilesystem();

        $testPath = $fs->tempnam(self::$testPath, 'test_');

        if ($subFolder !== null) {
            $testPath .= '/' . $subFolder;
        }

        $fs->remove($testPath);
        $fs->mkdir($testPath);

        return $testPath;
    }

    protected static function getTemplatePath(): string
    {
        return self::$templatePath;
    }

    protected static function getTemplateFilename(string $filename): string
    {
        return self::$templatePath . '/' . $filename;
    }

    protected static function getFilesystem(): Filesystem
    {
        return self::$filesystem;
    }

    /**
     * @param array<string, string> $files
     */
    protected static function createFiles(string $testPath, array $files): void
    {
        $fs = self::getFilesystem();

        foreach ($files as $target => $source) {
            $fs->copy(static::getFilename($source), $testPath . '/' . $target);
        }
    }
}
