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

use ArrayAccess;
use GsTYPO3\CorePatches\Config\Patches\PackagePatches;
use GsTYPO3\CorePatches\Exception\UnexpectedValueException;
use Iterator;
use IteratorAggregate;

/**
 * @implements IteratorAggregate<string, PackagePatches>
 * @implements ArrayAccess<string, PackagePatches>
 */
final class Patches implements PersistenceInterface, IteratorAggregate, ArrayAccess
{
    /**
     * @var array<string, PackagePatches>
     */
    private array $patches = [];

    /**
     * @param iterable<string, PackagePatches> $patches
     */
    public function __construct(
        iterable $patches = []
    ) {
        foreach ($patches as $package => $patch) {
            $this->put($package, $patch);
        }
    }

    /**
     * @param iterable<string, string> $patches
     */
    public function add(
        string $package,
        iterable $patches
    ): PackagePatches {
        $packagePatches = new PackagePatches($patches);

        $this->put($package, $packagePatches);

        return $packagePatches;
    }

    private function put(string $package, PackagePatches $packagePatches): void
    {
        if (!isset($this->patches[$package])) {
            $this->patches[$package] = $packagePatches;
        } else {
            foreach ($packagePatches as $description => $patch) {
                $this->patches[$package]->add($description, $patch);
            }
        }

        ksort($this->patches);
    }

    public function isEmpty(): bool
    {
        return $this->patches === [];
    }

    /**
     * @param iterable<string, string> $patches
     */
    public function remove(
        string $package,
        iterable $patches
    ): ?PackagePatches {
        if (!isset($this->patches[$package])) {
            return null;
        }

        $packagePatches = $this->patches[$package];

        foreach ($patches as $description => $patch) {
            $packagePatches->remove($description);
        }

        if ($packagePatches->isEmpty()) {
            unset($this->patches[$package]);

            return null;
        }

        return $packagePatches;
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
        $patches = [];

        foreach ($this->patches as $package => $packagePatches) {
            $patches[$package] = $packagePatches;
        }

        return $patches;
    }

    /**
     * @inheritDoc
     */
    public function jsonUnserialize(array $json): self
    {
        $this->patches = [];

        foreach ($json as $package => $patches) {
            if (!is_string($package)) {
                throw new UnexpectedValueException(sprintf('Package name is not a string (%s).', gettype($package)));
            }

            if (!is_array($patches)) {
                throw new UnexpectedValueException(sprintf('Patches is not an array (%s).', gettype($patches)));
            }

            $packagePatches = new PackagePatches();
            $packagePatches->jsonUnserialize($patches);

            $this->put($package, $packagePatches);
        }

        return $this;
    }

    /**
     * @return Iterator<string, PackagePatches>
     */
    public function getIterator(): Iterator
    {
        foreach ($this->patches as $package => $patches) {
            yield $package => $patches;
        }
    }

    public function offsetExists($offset): bool
    {
        return array_key_exists($offset, $this->patches);
    }

    public function offsetGet($offset): PackagePatches
    {
        return $this->patches[$offset];
    }

    public function offsetSet($offset, $value): void
    {
        $this->patches[$offset] = $value;
    }

    public function offsetUnset($offset): void
    {
        unset($this->patches[$offset]);
    }
}
