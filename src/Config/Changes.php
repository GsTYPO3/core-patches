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
    public function __construct(iterable $values = [])
    {
        foreach ($values as $value) {
            $this->put($value->getNumber(), $value);
        }
    }

    /**
     * @param iterable<int, string> $packages
     */
    public function add(
        int $number,
        int $revision = -1,
        iterable $packages = [],
        bool $tests = false
    ): Change {
        $change = new Change($number, $revision, $packages, $tests);

        $this->put($change->getNumber(), $change);

        return $change;
    }

    private function put(int $key, Change $change): void
    {
        $this->changes[$key] = $change;

        ksort($this->changes);
    }

    private function find(int $number): int
    {
        $index = 0;

        foreach ($this->changes as $change) {
            if ($change->getNumber() === $number) {
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

        foreach ($this->changes as $number => $change) {
            $changes[$number] = $change->jsonSerialize();
        }

        return $changes;
    }

    /**
     * @inheritDoc
     */
    public function jsonUnserialize(array $json): self
    {
        $this->changes = [];

        foreach ($json as $singleJson) {
            // For BC only
            if (is_int($singleJson)) {
                $change = new Change($singleJson);
            } else {
                if (!is_array($singleJson)) {
                    throw new UnexpectedValueException(sprintf('Change is not an array (%s).', gettype($singleJson)));
                }

                $change = new Change(0);
                $change->jsonUnserialize($singleJson);
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
