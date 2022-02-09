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

use GsTYPO3\CorePatches\Config;
use GsTYPO3\CorePatches\Config\Patches\PackagePatches;
use GsTYPO3\CorePatches\Exception\UnexpectedValueException;
use Iterator;
use IteratorAggregate;

/**
 * @implements IteratorAggregate<string, PackagePatches>
 */
final class Patches implements ConfigAwareInterface, PersistenceInterface, IteratorAggregate
{
    private Config $config;

    /**
     * @var array<string, PackagePatches>
     */
    private array $patches = [];

    /**
     * @param iterable<string, PackagePatches> $patches
     */
    public function __construct(
        Config $config,
        iterable $patches = []
    ) {
        $this->config = $config;

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
        $packagePatches = new PackagePatches($this->config, /*$package,*/ $patches);

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
     * @inheritDoc
     */
    public function getConfig(): Config
    {
        return $this->config;
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
            $patches[$package] = $packagePatches/*->jsonSerialize()*/;
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

            $packagePatches = new PackagePatches($this->config);
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
}
