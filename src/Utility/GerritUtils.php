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

namespace GsTYPO3\CorePatches\Utility;

use Composer\Util\Http\Response;
use Composer\Util\HttpDownloader;
use GsTYPO3\CorePatches\Exception\InvalidResponseException;
use GsTYPO3\CorePatches\Exception\UnexpectedResponseException;
use GsTYPO3\CorePatches\Exception\UnexpectedValueException;

final class GerritUtils
{
    private const BASE_URL = 'https://review.typo3.org/';

    /** @var HttpDownloader */
    private $downloader;
    /** @var array<string, array<string, mixed>>|null */
    private $changeInfo;

    /**
     * @param HttpDownloader $downloader    A HttpDownloader instance
     */
    public function __construct(HttpDownloader $downloader)
    {
        $this->downloader = $downloader;
    }

    /**
     * @param string    $changeId   The change ID
     * @return mixed[]              The change info
     * @throws InvalidResponseException
     *
     * @see https://review.typo3.org/Documentation/rest-api-changes.html#change-id
     * @see https://review.typo3.org/Documentation/rest-api-changes.html#change-info
     * @see https://review.typo3.org/Documentation/rest-api-changes.html#get-change
     */
    public function getChange(string $changeId): array
    {
        if (!isset($this->changeInfo[$changeId])) {
            // Support for full review URLs
            if (
                preg_match(
                    '#^' . preg_quote(self::BASE_URL) . 'c/Packages/TYPO3.CMS/\+/(\d+)#',
                    $changeId,
                    $matches
                ) === 1
            ) {
                $changeId = $matches[1];
            }

            $url = sprintf(self::BASE_URL . 'changes/%s', $changeId);
            $response = $this->downloader->get($url);
            $body = $this->checkResponse($response);

            // Remove leading markers
            if (strpos($body, ')]}\'') === 0) {
                $body = substr($body, 4);
            }

            $changeInfo = json_decode(trim($body), true);

            if ($changeInfo === null || !is_array($changeInfo)) {
                throw new InvalidResponseException('Error invalid response.', 1640784346);
            }
            $this->changeInfo[$changeId] = $changeInfo;
        }

        return $this->changeInfo[$changeId];
    }

    /**
     * @param string    $changeId   The change ID
     * @return string               The normalized subject
     * @throws UnexpectedValueException
     * @throws InvalidResponseException
     *
     * @see https://review.typo3.org/Documentation/rest-api-changes.html#change-id
     * @see https://review.typo3.org/Documentation/rest-api-changes.html#change-info
     */
    public function getSubject(string $changeId): string
    {
        $changeInfo = $this->getChange($changeId);

        if (!is_string($subject = ($changeInfo['subject'] ?? null))) {
            throw new UnexpectedValueException('Subject was not found.', 1640944473);
        }

        if (($normalizedSubject = preg_replace('/^\[.+?\] /', '', $subject)) === null) {
            throw new UnexpectedValueException(sprintf('Subject "%s" could not be normalized.', $subject), 1640944474);
        }

        return $normalizedSubject;
    }

    /**
     * @param string    $changeId   The change ID
     * @return int                  The legacy numeric ID
     * @throws UnexpectedValueException
     * @throws InvalidResponseException
     *
     * @see https://review.typo3.org/Documentation/rest-api-changes.html#change-id
     * @see https://review.typo3.org/Documentation/rest-api-changes.html#change-info
     */
    public function getNumericId(string $changeId): int
    {
        $changeInfo = $this->getChange($changeId);

        if (!is_int($numericId = ($changeInfo['_number'] ?? null))) {
            throw new UnexpectedValueException('Number was not found.', 1640944475);
        }

        return $numericId;
    }

    /**
     * @param string    $changeId   The change ID
     * @return string               The patch
     * @throws InvalidResponseException
     *
     * @see https://review.typo3.org/Documentation/rest-api-changes.html#change-id
     * @see https://review.typo3.org/Documentation/rest-api-changes.html#get-patch
     */
    public function getPatch(string $changeId): string
    {
        $url = sprintf(self::BASE_URL . 'changes/%s/revisions/current/patch', $changeId);
        $response = $this->downloader->get($url);
        $patch = base64_decode($this->checkResponse($response), true);

        if ($patch === false) {
            throw new InvalidResponseException('Error invalid response.');
        }

        return $patch;
    }

    /**
     * Checks the response against a status code and returns the response body.
     *
     * @param Response  $response           The response object
     * @param int       $expectedStatusCode The expected status code, defaults to 200
     * @return string                       The body
     * @throws UnexpectedResponseException
     */
    private function checkResponse(Response $response, int $expectedStatusCode = 200): string
    {
        if (($statusCode = $response->getStatusCode()) !== $expectedStatusCode) {
            throw new UnexpectedResponseException(sprintf(
                'Unexpected status code "%d", expected "%d".',
                $statusCode,
                $expectedStatusCode
            ));
        }

        if (!is_string($body = $response->getBody())) {
            throw new UnexpectedResponseException(sprintf(
                'Unexpected response "%s".',
                gettype($body)
            ));
        }

        return $body;
    }
}
