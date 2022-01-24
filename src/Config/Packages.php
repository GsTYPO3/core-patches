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

use Iterator;
use IteratorAggregate;

/**
 * @implements IteratorAggregate<int, string>
 */
final class Packages implements PersistenceInterface, IteratorAggregate
{
    /**
     * @var array<int, string>
     */
    private array $packages = [];

    /**
     * @param iterable<int, string> $values
     */
    public function __construct(iterable $values = [])
    {
        foreach ($values as $value) {
            $this->add($value);
        }
    }

    public function add(string $value): void
    {
        $this->packages[] = $value;

        sort($this->packages);
    }

    private function find(string $value): int
    {
        foreach ($this->packages as $key => $package) {
            if ($package === $value) {
                return $key;
            }
        }

        return -1;
    }

    public function has(string $value): bool
    {
        return $this->find($value) > -1;
    }

    public function isEmpty(): bool
    {
        return $this->packages === [];
    }

    public function remove(string $value): ?string
    {
        return ($index = $this->find($value)) > -1 ? array_splice($this->packages, $index, 1)[0] : null;
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
            if (is_string($singleJson)) {
                $this->add($singleJson);
            }
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
