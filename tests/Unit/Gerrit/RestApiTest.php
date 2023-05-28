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
use Composer\Downloader\TransportException;
use Composer\Factory;
use Composer\IO\BufferIO;
use Composer\Util\Http\Response;
use Composer\Util\HttpDownloader;
use Exception;
use GsTYPO3\CorePatches\Exception\InvalidResponseException;
use GsTYPO3\CorePatches\Exception\UnexpectedResponseException;
use GsTYPO3\CorePatches\Gerrit\RestApi;
use GsTYPO3\CorePatches\Tests\Unit\TestCase;
use Iterator;
use Prophecy\Argument;
use Prophecy\PhpUnit\ProphecyTrait;
use RuntimeException;

/**
 * @medium
 * @covers \GsTYPO3\CorePatches\Gerrit\RestApi
 * @uses \GsTYPO3\CorePatches\Gerrit\Entity\AbstractEntity
 * @uses \GsTYPO3\CorePatches\Gerrit\Entity\ChangeInfo
 * @uses \GsTYPO3\CorePatches\Gerrit\Entity\IncludedInInfo
 */
final class RestApiTest extends TestCase
{
    use ProphecyTrait;

    private string $previousWorkingDir;

    private string $testWorkingDir;

    private BufferIO $bufferIO;

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

        $this->bufferIO = new BufferIO();
        $this->config = Factory::createConfig($this->bufferIO);
        $this->gerritRestApi = new RestApi(Factory::createHttpDownloader($this->bufferIO, $this->config));
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
        string $branch,
        string $changeId,
        string $subject,
        string $subjectNormalized,
        int $number
    ): void {
        $changeInfo = $this->gerritRestApi->getChange($rawChangeId);

        self::assertSame($id, $changeInfo->id);
        self::assertSame($branch, $changeInfo->branch);
        self::assertSame($changeId, $changeInfo->changeId);
        self::assertSame($subject, $changeInfo->subject);
        self::assertSame($number, $changeInfo->number);

        self::assertSame($branch, $this->gerritRestApi->getBranch($rawChangeId));
        self::assertSame($subjectNormalized, $this->gerritRestApi->getSubject($rawChangeId));
        self::assertSame($number, $this->gerritRestApi->getNumericId($rawChangeId));
    }

    /**
     * @return Iterator<string, array{
     *   rawChangeId: string,
     *   id: string,
     *   branch: string,
     *   changeId: string,
     *   subject: string,
     *   subjectNormalized: string,
     *   number: int,
     * }>
     */
    public function changeIdsProvider(): Iterator
    {
        yield 'numeric change id' => [
            'rawChangeId' => '73021',
            'id' => 'Packages%2FTYPO3.CMS~main~I84baa3df4b3a96cacbb3e686e4c85562d67422df',
            'branch' => 'main',
            'changeId' => 'I84baa3df4b3a96cacbb3e686e4c85562d67422df',
            'subject' => '[TASK] Test change for gilbertsoft/typo3-core-patches',
            'subjectNormalized' => 'Test change for gilbertsoft/typo3-core-patches',
            'number' => 73021,
        ];
        yield 'change url' => [
            'rawChangeId' => 'https://review.typo3.org/c/Packages/TYPO3.CMS/+/73021',
            'id' => 'Packages%2FTYPO3.CMS~main~I84baa3df4b3a96cacbb3e686e4c85562d67422df',
            'branch' => 'main',
            'changeId' => 'I84baa3df4b3a96cacbb3e686e4c85562d67422df',
            'subject' => '[TASK] Test change for gilbertsoft/typo3-core-patches',
            'subjectNormalized' => 'Test change for gilbertsoft/typo3-core-patches',
            'number' => 73021,
        ];
    }

    public function testGetIncludedIn(): void
    {
        $includedInInfo = $this->gerritRestApi->getIncludedIn('73021');

        self::assertSame([], $includedInInfo->branches);
        self::assertSame([], $includedInInfo->tags);
        self::assertSame([], $includedInInfo->external);
    }

    public function testGetPatch(): void
    {
        self::assertStringContainsString(
            'Subject: [PATCH] [TASK] Test change for gilbertsoft/typo3-core-patches',
            $this->gerritRestApi->getPatch('73021')
        );
    }

    public function testGetPatchProperlyHandlesNonBase64Responses(): void
    {
        $responseProphecy = $this->prophesize(Response::class);
        $responseProphecy->getBody()->willReturn('invalid base64');

        $httpDownloaderProphecy = $this->prophesize(HttpDownloader::class);
        $httpDownloaderProphecy->get(
            Argument::type('string'),
            Argument::type('array')
        )->willReturn($responseProphecy->reveal());

        $restApi = new RestApi($httpDownloaderProphecy->reveal());

        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionCode(0);
        $this->expectExceptionMessage('Error invalid response.');

        $restApi->getPatch('12345');
    }

    public function testTransportExceptionIsProperlyForwarded(): void
    {
        $objectProphecy = $this->prophesize(HttpDownloader::class);
        $objectProphecy->get(Argument::type('string'), Argument::type('array'))->willThrow(
            new TransportException('Test TransportException')
        );

        $restApi = new RestApi($objectProphecy->reveal());

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionCode(0);
        $this->expectExceptionMessage('Test TransportException');

        $restApi->getPatch('12345');
    }

    public function testOtherExceptionIsProperlyForwarded(): void
    {
        $objectProphecy = $this->prophesize(HttpDownloader::class);
        $objectProphecy->get(Argument::type('string'), Argument::type('array'))->willThrow(
            new Exception('Test Exception')
        );

        $restApi = new RestApi($objectProphecy->reveal());

        $this->expectException(UnexpectedResponseException::class);
        $this->expectExceptionCode(0);
        $this->expectExceptionMessage('Test Exception');

        $restApi->getPatch('12345');
    }

    public function testWrongBodyType(): void
    {
        $responseProphecy = $this->prophesize(Response::class);
        $responseProphecy->getBody()->willReturn(null);

        $httpDownloaderProphecy = $this->prophesize(HttpDownloader::class);
        $httpDownloaderProphecy->get(
            Argument::type('string'),
            Argument::type('array')
        )->willReturn($responseProphecy->reveal());

        $restApi = new RestApi($httpDownloaderProphecy->reveal());

        $this->expectException(InvalidResponseException::class);
        $this->expectExceptionCode(0);
        $this->expectExceptionMessage('Unexpected response "NULL".');

        $restApi->getPatch('12345');
    }
}
