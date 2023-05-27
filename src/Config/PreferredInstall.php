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

namespace GsTYPO3\CorePatches\Config;

use GsTYPO3\CorePatches\Exception\UnexpectedValueException;
use Iterator;
use IteratorAggregate;

/**
 * @implements IteratorAggregate<string, string>
 */
final class PreferredInstall implements PersistenceInterface, IteratorAggregate
{
    /**
     * @var string
     */
    public const METHOD_DIST = 'dist';

    /**
     * @var string
     */
    public const METHOD_SOURCE = 'source';

    /**
     * @var string
     */
    public const METHOD_AUTO = 'auto';

    /**
     * @var array<string, string>
     */
    private array $preferredInstall = [];

    /**
     * @param iterable<string, string> $preferredInstall
     */
    public function __construct(
        iterable $preferredInstall = []
    ) {
        $preferredInstallArray = [];

        foreach ($preferredInstall as $packageName => $installMethod) {
            $preferredInstallArray[$packageName] = $installMethod;
        }

        foreach (array_reverse($preferredInstallArray, true) as $packageName => $installMethod) {
            $this->add($packageName, $installMethod);
        }
    }

    public function add(string $packageName, string $installMethod): void
    {
        $this->preferredInstall = array_merge([$packageName => $installMethod], $this->preferredInstall);
    }

    public function has(string $packageName, string $installMethod): bool
    {
        return isset($this->preferredInstall[$packageName]) && $this->preferredInstall[$packageName] === $installMethod;
    }

    public function isEmpty(): bool
    {
        return $this->preferredInstall === [];
    }

    /**
     * @return array<string, string>|null
     */
    public function remove(string $packageName): ?array
    {
        if (isset($this->preferredInstall[$packageName])) {
            $installMethod = $this->preferredInstall[$packageName];
            unset($this->preferredInstall[$packageName]);

            return [$packageName => $installMethod];
        }

        return null;
    }

    /**
     * Returns a representation that can be natively converted to JSON, which is
     * called when invoking json_encode.
     *
     * @return string[]
     *
     * @see \JsonSerializable
     */
    public function jsonSerialize(): array
    {
        return $this->preferredInstall;
    }

    /**
     * @inheritDoc
     */
    public function jsonUnserialize(array $json): self
    {
        $this->preferredInstall = [];

        foreach (array_reverse($json, true) as $packageName => $installMethod) {
            if (!is_string($packageName)) {
                throw new UnexpectedValueException(sprintf(
                    'Package is not a string (%s).',
                    var_export($packageName, true)
                ));
            }

            if (!is_string($installMethod)) {
                throw new UnexpectedValueException(sprintf(
                    'Install method is not a string (%s).',
                    var_export($installMethod, true)
                ));
            }

            $this->add($packageName, $installMethod);
        }

        return $this;
    }

    /**
     * @return Iterator<string, string>
     */
    public function getIterator(): Iterator
    {
        foreach ($this->preferredInstall as $packageName => $installMethod) {
            yield $packageName => $installMethod;
        }
    }
}
