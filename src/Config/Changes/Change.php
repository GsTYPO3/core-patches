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

namespace GsTYPO3\CorePatches\Config\Changes;

use GsTYPO3\CorePatches\Config\Packages;
use GsTYPO3\CorePatches\Config\PersistenceInterface;
use GsTYPO3\CorePatches\Exception\UnexpectedValueException;

final class Change implements PersistenceInterface
{
    /**
     * @var string
     */
    private const REVISION = 'revision';

    /**
     * @var string
     */
    private const PACKAGES = 'packages';

    /**
     * @var string
     */
    private const TESTS = 'tests';

    /**
     * @var string
     */
    private const PATCH_DIR = 'patch-directory';

    private int $number;

    private int $revision;

    private Packages $packages;

    private bool $tests;

    private string $patchDirectory = '';

    /**
     * @param iterable<int, string> $packages
     */
    public function __construct(
        int $number,
        iterable $packages = [],
        bool $tests = false,
        string $patchDirectory = '',
        int $revision = -1
    ) {
        $this->number = $number;
        $this->revision = $revision;
        $this->packages = new Packages($packages);
        $this->tests = $tests;
        $this->patchDirectory = $patchDirectory;
    }

    public function getNumber(): int
    {
        return $this->number;
    }

    public function getRevision(): int
    {
        return $this->revision;
    }

    public function getPackages(): Packages
    {
        return $this->packages;
    }

    public function getTests(): bool
    {
        return $this->tests;
    }

    public function getPatchDirectory(): string
    {
        return $this->patchDirectory;
    }

    /**
     * Returns a representation that can be natively converted to JSON, which is
     * called when invoking json_encode.
     *
     * @return array{revision?: int, packages: string[], tests?: true, patch-directory?: string}
     *
     * @see \JsonSerializable
     */
    public function jsonSerialize(): array
    {
        $array = [];

        if ($this->revision > -1) {
            $array[self::REVISION] = $this->revision;
        }

        $array[self::PACKAGES] = $this->packages->jsonSerialize();

        if ($this->tests) {
            $array[self::TESTS] = $this->tests;
        }

        if ($this->patchDirectory !== '') {
            $array[self::PATCH_DIR] = $this->patchDirectory;
        }

        return $array;
    }

    /**
     * @inheritDoc
     */
    public function jsonUnserialize(array $json): self
    {
        if (!is_int($revision = $json[self::REVISION] ?? null)) {
            $revision = -1;
        }

        $this->revision = $revision;

        if (!is_array($packages = $json[self::PACKAGES] ?? null)) {
            throw new UnexpectedValueException('Packages is not an array or missing.');
        }

        $this->packages->jsonUnserialize($packages);

        if (!is_bool($tests = $json[self::TESTS] ?? null)) {
            $tests = false;
        }

        $this->tests = $tests;

        if (!is_string($patchDirectory = $json[self::PATCH_DIR] ?? null)) {
            $patchDirectory = '';
        }

        $this->patchDirectory = $patchDirectory;

        return $this;
    }
}
