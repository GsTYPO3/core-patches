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

namespace GsTYPO3\CorePatches\Tests\Unit\Gerrit;

use Composer\Config;
use Composer\Factory;
use Composer\IO\BufferIO;
use GsTYPO3\CorePatches\Gerrit\RestApi;
use GsTYPO3\CorePatches\Tests\Unit\TestCase;
use RuntimeException;
use Iterator;

final class RestApiTest extends TestCase
{
    private string $previousWorkingDir;

    private string $testWorkingDir;

    private BufferIO $io;

    private Config $config;

    private RestApi $gerritRestApi;

    protected function setUp(): void
    {
        parent::setUp();

        if (($previousWorkingDir = getcwd()) === false) {
            throw new RuntimeException('Unable to determine current directory.', 1_636_451_408);
        }

        $this->previousWorkingDir = $previousWorkingDir;
        $this->testWorkingDir = self::getTestPath();
        chdir($this->testWorkingDir);

        self::createFiles($this->testWorkingDir, [
            'composer.json' => 'FIX:composer.json',
        ]);

        $this->io = new BufferIO();
        $this->config = Factory::createConfig($this->io);
        $this->gerritRestApi = new RestApi(Factory::createHttpDownloader($this->io, $this->config));
    }

    protected function tearDown(): void
    {
        chdir($this->previousWorkingDir);

        parent::tearDown();
    }

    /**
     * @dataProvider changeIdsProvider
     */
    public function testGetChange(
        string $rawChangeId,
        string $id,
        string $changeId,
        string $subject,
        int $number
    ): void {
        $changeInfo = $this->gerritRestApi->getChange($rawChangeId);

        self::assertSame($id, $changeInfo->id);
        self::assertSame($changeId, $changeInfo->changeId);
        self::assertSame($subject, $changeInfo->subject);
        self::assertSame($number, $changeInfo->number);
    }

    /**
     * @return Iterator<string, array<string, array<int, string>|int|string|bool>>
     */
    public function changeIdsProvider(): Iterator
    {
        yield 'numeric change id' => [
            'rawChangeId' => '73021',
            'id' => 'Packages%2FTYPO3.CMS~main~I84baa3df4b3a96cacbb3e686e4c85562d67422df',
            'changeId' => 'I84baa3df4b3a96cacbb3e686e4c85562d67422df',
            'subject' => '[TASK] Test change for gilbertsoft/typo3-core-patches',
            'number' => 73021,
        ];
        yield 'change url' => [
            'rawChangeId' => 'https://review.typo3.org/c/Packages/TYPO3.CMS/+/73021',
            'id' => 'Packages%2FTYPO3.CMS~main~I84baa3df4b3a96cacbb3e686e4c85562d67422df',
            'changeId' => 'I84baa3df4b3a96cacbb3e686e4c85562d67422df',
            'subject' => '[TASK] Test change for gilbertsoft/typo3-core-patches',
            'number' => 73021,
        ];
    }
}
