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

namespace GsTYPO3\CorePatches\Gerrit;

use Composer\Downloader\TransportException;
use Composer\Util\HttpDownloader;
use Exception;
use GsTYPO3\CorePatches\Exception\InvalidResponseException;
use GsTYPO3\CorePatches\Exception\UnexpectedResponseException;
use GsTYPO3\CorePatches\Exception\UnexpectedValueException;
use GsTYPO3\CorePatches\Gerrit\Entity\ChangeInfo;
use GsTYPO3\CorePatches\Gerrit\Entity\IncludedInInfo;

final class RestApi
{
    /**
     * @var string
     */
    private const REST_API = 'https://review.typo3.org';

    /**
     * @var string
     */
    protected const MAGIC_PREFIX_LINE = ")]}'\n";

    private ?HttpDownloader $httpDownloader;

    /**
     * @var array<string, ChangeInfo>
     */
    private array $changeInfo = [];

    /**
     * @var array<string, IncludedInInfo>
     */
    private array $includedInInfo = [];

    public function __construct(HttpDownloader $httpDownloader)
    {
        $this->httpDownloader = $httpDownloader;
    }

    /**
     * @see https://review.typo3.org/Documentation/rest-api-changes.html#change-id
     * @see https://review.typo3.org/Documentation/rest-api-changes.html#get-change
     */
    public function getChange(string $changeId): ChangeInfo
    {
        $changeId = $this->extractChangeId($changeId);

        if (!isset($this->changeInfo[$changeId])) {
            $this->changeInfo[$changeId] = ChangeInfo::fromJson(
                $this->get(sprintf(self::REST_API . '/changes/%s', $changeId))
            );
        }

        return $this->changeInfo[$changeId];
    }

    /**
     * @see https://review.typo3.org/Documentation/rest-api-changes.html#change-id
     */
    public function getBranch(string $changeId): string
    {
        return $this->getChange($changeId)->branch;
    }

    /**
     * @return string                   The normalized subject
     * @throws UnexpectedValueException
     *
     * @see https://review.typo3.org/Documentation/rest-api-changes.html#change-id
     */
    public function getSubject(string $changeId): string
    {
        $subject = $this->getChange($changeId)->subject;

        if (($normalizedSubject = preg_replace('#^\[.+?\] #', '', $subject)) === null) {
            // @codeCoverageIgnoreStart
            throw new UnexpectedValueException(sprintf('Subject "%s" could not be normalized.', $subject));
            // @codeCoverageIgnoreEnd
        }

        return $normalizedSubject;
    }

    /**
     * @see https://review.typo3.org/Documentation/rest-api-changes.html#change-id
     */
    public function getNumericId(string $changeId): int
    {
        return $this->getChange($changeId)->number;
    }

    /**
     * @see https://review.typo3.org/Documentation/rest-api-changes.html#change-id
     * @see https://review.typo3.org/Documentation/rest-api-changes.html#get-included-in
     */
    public function getIncludedIn(string $changeId): IncludedInInfo
    {
        $changeId = $this->extractChangeId($changeId);

        if (!isset($this->includedInInfo[$changeId])) {
            $this->includedInInfo[$changeId] = IncludedInInfo::fromJson(
                $this->get(sprintf(self::REST_API . '/changes/%s/in', $changeId))
            );
        }

        return $this->includedInInfo[$changeId];
    }

    /**
     * @throws InvalidResponseException
     *
     * @see https://review.typo3.org/Documentation/rest-api-changes.html#change-id
     * @see https://review.typo3.org/Documentation/rest-api-changes.html#get-patch
     */
    public function getPatch(string $changeId, int $revisionId = -1): string
    {
        $changeId = $this->extractChangeId($changeId);

        if ($revisionId === -1) {
            $revisionId = 'current';
        }

        $patch = base64_decode(
            $this->get(sprintf(self::REST_API . '/changes/%s/revisions/%s/patch', $changeId, $revisionId), true),
            true
        );

        if (!is_string($patch)) {
            throw new InvalidResponseException('Error invalid response.');
        }

        return $patch;
    }

    /**
     * @see https://review.typo3.org/Documentation/rest-api-changes.html#change-id
     */
    private function extractChangeId(string $changeId): string
    {
        // Support for full review URLs
        if (
            preg_match(
                '#^' . preg_quote(self::REST_API, '#') . '/c/Packages/TYPO3.CMS/\+/(\d+)#',
                $changeId,
                $matches
            ) === 1
        ) {
            return $matches[1];
        }

        return $changeId;
    }

    /**
     * @throws UnexpectedResponseException
     * @throws InvalidResponseException
     */
    private function get(string $url, bool $raw = false): string
    {
        try {
            $options = [];

            if (!$raw) {
                // Change Accept to receive compact JSON
                $options['http']['header'][] = 'Accept: application/json';
            }

            $body = $this->httpDownloader instanceof HttpDownloader
                ? $this->httpDownloader->get($url, $options)->getBody()
                : file_get_contents($url);
        } catch (TransportException $transportException) {
            throw new UnexpectedResponseException($transportException->getMessage(), 0, $transportException);
        } catch (Exception $exception) {
            throw new UnexpectedResponseException('Could not read ' . $url . "\n\n" . $exception->getMessage());
        }

        if (!is_string($body)) {
            throw new InvalidResponseException(sprintf(
                'Unexpected response "%s".',
                gettype($body)
            ));
        }

        // Strip magic prefix line
        if (strncmp($body, self::MAGIC_PREFIX_LINE, strlen(self::MAGIC_PREFIX_LINE)) === 0) {
            return substr($body, strlen(self::MAGIC_PREFIX_LINE));
        }

        return $body;
    }
}
