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
 * @implements IteratorAggregate<int, string>
 */
class Packages implements PersistenceInterface, IteratorAggregate
{
    /**
     * @var array<int, string>
     */
    private array $packages = [];

    /**
     * @param iterable<int, string> $packages
     */
    public function __construct(
        iterable $packages = []
    ) {
        foreach ($packages as $package) {
            $this->add($package);
        }
    }

    public function add(string $package): void
    {
        $this->packages[] = $package;

        sort($this->packages);
    }

    private function find(string $needle): int
    {
        foreach ($this->packages as $key => $package) {
            if ($package === $needle) {
                return $key;
            }
        }

        return -1;
    }

    public function has(string $package): bool
    {
        return $this->find($package) > -1;
    }

    public function isEmpty(): bool
    {
        return $this->packages === [];
    }

    public function remove(string $package): ?string
    {
        return ($index = $this->find($package)) > -1 ? array_splice($this->packages, $index, 1)[0] : null;
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
        return $this->packages;
    }

    /**
     * @inheritDoc
     */
    public function jsonUnserialize(array $json): self
    {
        $this->packages = [];

        foreach ($json as $singleJson) {
            if (!is_string($singleJson)) {
                throw new UnexpectedValueException(sprintf(
                    'Package is not a string (%s).',
                    var_export($singleJson, true)
                ));
            }

            $this->add($singleJson);
        }

        return $this;
    }

    /**
     * @return Iterator<string>
     */
    public function getIterator(): Iterator
    {
        foreach ($this->packages as $package) {
            yield $package;
        }
    }
}
