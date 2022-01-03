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
use RuntimeException;
use UnexpectedValueException;

final class Gerrit
{
    private const BASE_URL = 'https://review.typo3.org/';

    /** @var HttpDownloader */
    private $downloader;

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
     * @throws RuntimeException
     * @see https://review.typo3.org/Documentation/rest-api-changes.html#change-id
     * @see https://review.typo3.org/Documentation/rest-api-changes.html#change-info
     * @see https://review.typo3.org/Documentation/rest-api-changes.html#get-change
     */
    public function getChange(string $changeId): array
    {
        $url = sprintf(self::BASE_URL . 'changes/%s', $changeId);
        $response = $this->downloader->get($url);
        //$body = file_get_contents($url);
        $body = $this->checkResponse($response);

        // Remove leading markers
        if (strpos($body, ')]}\'') === 0) {
            $body = substr($body, 4);
        }

        $changeInfo = json_decode(trim($body), true);

        if ($changeInfo === null || !is_array($changeInfo)) {
            throw new RuntimeException('Error invalid response.', 1640784346);
        }

        return $changeInfo;
    }

    /**
     * @param string    $changeId   The change ID
     * @return string               The normalized subject
     * @throws UnexpectedValueException
     * @see https://review.typo3.org/Documentation/rest-api-changes.html#change-id
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
     * @return string               The patch
     * @throws RuntimeException
     * @see https://review.typo3.org/Documentation/rest-api-changes.html#change-id
     * @see https://review.typo3.org/Documentation/rest-api-changes.html#get-patch
     */
    public function getPatch(string $changeId): string
    {
        $url = sprintf(self::BASE_URL . 'changes/%s/revisions/current/patch', $changeId);
        $response = $this->downloader->get($url);
        //$body = file_get_contents($url);
        $patch = base64_decode($this->checkResponse($response), true);

        if ($patch === false) {
            throw new RuntimeException('Error invalid response.', 1640784347);
        }

        return $patch;
    }

    /**
     * Checks the response against a status code and returns the response body.
     *
     * @param Response  $response           The response object
     * @param int       $expectedStatusCode The expected status code, defaults to 200
     * @return string                       The body
     * @throws RuntimeException
     */
    private function checkResponse(Response $response, int $expectedStatusCode = 200): string
    {
        if (($statusCode = $response->getStatusCode()) !== $expectedStatusCode) {
            throw new RuntimeException(sprintf('Unexpected status code "%d".', $statusCode), 1640783526);
        }

        if (!is_string($body = $response->getBody())) {
            throw new RuntimeException(sprintf('Unexpected answer "%s".', gettype($body)), 1640783527);
        }

        return $body;
    }
}
