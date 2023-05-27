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

namespace GsTYPO3\CorePatches\Config\Patches;

use Countable;
use GsTYPO3\CorePatches\Config\PersistenceInterface;
use GsTYPO3\CorePatches\Exception\UnexpectedValueException;
use Iterator;
use IteratorAggregate;

/**
 * @implements IteratorAggregate<string, string>
 */
final class PackagePatches implements PersistenceInterface, IteratorAggregate, Countable
{
    /**
     * @var array<string, string>
     */
    private array $patches = [];

    /**
     * @param iterable<string, string> $patches
     */
    public function __construct(
        iterable $patches = []
    ) {
        foreach ($patches as $description => $patch) {
            $this->add($description, $patch);
        }
    }

    public function add(string $description, string $patch): void
    {
        $this->patches[$description] = $patch;

        ksort($this->patches);
    }

    private function find(string $needle): int
    {
        $index = 0;

        foreach (array_keys($this->patches) as $description) {
            if ($description === $needle) {
                break;
            }

            ++$index;
        }

        return $index < count($this->patches) ? $index : -1;
    }

    public function isEmpty(): bool
    {
        return $this->patches === [];
    }

    public function remove(string $description): ?string
    {
        return ($index = $this->find($description)) > -1 ? array_splice($this->patches, $index, 1)[$description] : null;
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
        return $this->patches;
    }

    /**
     * @inheritDoc
     */
    public function jsonUnserialize(array $json): self
    {
        $this->patches = [];

        foreach ($json as $description => $patch) {
            if (!is_string($description)) {
                throw new UnexpectedValueException(sprintf(
                    'Description is not a string (%s).',
                    var_export($description, true)
                ));
            }

            if (!is_string($patch)) {
                throw new UnexpectedValueException(sprintf(
                    'Patch is not a string (%s).',
                    var_export($patch, true)
                ));
            }

            $this->add($description, $patch);
        }

        return $this;
    }

    /**
     * @return Iterator<string, string>
     */
    public function getIterator(): Iterator
    {
        foreach ($this->patches as $description => $patch) {
            yield $description => $patch;
        }
    }

    public function count(): int
    {
        return count($this->patches);
    }
}
