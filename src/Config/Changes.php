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

use GsTYPO3\CorePatches\Config\Changes\Change;
use GsTYPO3\CorePatches\Exception\UnexpectedValueException;
use Iterator;
use IteratorAggregate;

/**
 * @implements IteratorAggregate<int, Change>
 */
final class Changes implements PersistenceInterface, IteratorAggregate
{
    /**
     * @var array<int, Change>
     */
    private array $changes = [];

    /**
     * @param iterable<int, Change> $values
     */
    public function __construct(
        iterable $values = []
    ) {
        foreach ($values as $value) {
            $this->put($value->getNumber(), $value);
        }
    }

    /**
     * @param iterable<int, string> $packages
     */
    public function add(
        int $number,
        iterable $packages = [],
        bool $tests = false,
        string $patchDirectory = '',
        int $revision = -1
    ): Change {
        $change = new Change($number, $packages, $tests, $patchDirectory, $revision);

        $this->put($change->getNumber(), $change);

        return $change;
    }

    private function put(int $key, Change $change): void
    {
        $this->changes[$key] = $change;

        ksort($this->changes);
    }

    private function find(int $needle): int
    {
        $index = 0;

        foreach ($this->changes as $change) {
            if ($change->getNumber() === $needle) {
                break;
            }

            ++$index;
        }

        return $index < count($this->changes) ? $index : -1;
    }

    public function has(int $number): bool
    {
        return $this->find($number) > -1;
    }

    public function isEmpty(): bool
    {
        return $this->changes === [];
    }

    public function remove(int $number): ?Change
    {
        return ($index = $this->find($number)) > -1 ? (array_splice($this->changes, $index, 1)[0] ?? null) : null;
    }

    /**
     * Returns a representation that can be natively converted to JSON, which is
     * called when invoking json_encode.
     *
     * @return mixed[]
     *
     * @see \JsonSerializable
     */
    public function jsonSerialize(): array
    {
        $changes = [];

        foreach ($this->changes as $change) {
            $changes[$change->getNumber()] = $change;
        }

        return $changes;
    }

    /**
     * @inheritDoc
     */
    public function jsonUnserialize(array $json): self
    {
        $this->changes = [];

        foreach ($json as $changeNumber => $changeConfig) {
            // For BC only
            if (is_int($changeConfig)) {
                $change = new Change($changeConfig);
            } else {
                if (!is_array($changeConfig)) {
                    throw new UnexpectedValueException(sprintf('Change is not an array (%s).', gettype($changeConfig)));
                }

                $change = new Change($changeNumber);
                $change->jsonUnserialize($changeConfig);
            }

            $this->put($change->getNumber(), $change);
        }

        return $this;
    }

    /**
     * @return Iterator<int, Change>
     */
    public function getIterator(): Iterator
    {
        foreach ($this->changes as $key => $value) {
            yield $key => $value;
        }
    }
}
