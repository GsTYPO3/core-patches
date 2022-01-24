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

namespace GsTYPO3\CorePatches;

use Composer\Config\JsonConfigSource;
use Composer\Json\JsonFile;
use GsTYPO3\CorePatches\Config\Changes;
use GsTYPO3\CorePatches\Config\Packages;

final class Config
{
    /**
     * @var string
     */
    private const EXTRA = 'extra';

    /**
     * @var string
     */
    private const PACKAGE = 'gilbertsoft/typo3-core-patches';

    /**
     * @var string
     */
    private const CHANGES = 'applied-changes';

    /**
     * @var string
     */
    private const PREFERRED_INSTALL_CHANGED = 'preferred-install-changed';

    private Changes $changes;

    private Packages $preferredInstallChanged;

    public function __construct()
    {
        $this->changes = new Changes();
        $this->preferredInstallChanged = new Packages();
    }

    public function getChanges(): Changes
    {
        return $this->changes;
    }

    public function getPreferredInstallChanged(): Packages
    {
        return $this->preferredInstallChanged;
    }

    public function load(JsonFile $jsonFile): self
    {
        $config = $jsonFile->read();

        if (
            is_array($config)
            && is_array($config[self::EXTRA] ?? null)
            && is_array($config[self::EXTRA][self::PACKAGE] ?? null)
        ) {
            $packageConfig = $config[self::EXTRA][self::PACKAGE];
        } else {
            $packageConfig = [];
        }

        if (!is_array($changes = $packageConfig[self::CHANGES] ?? null)) {
            $changes = [];
        }

        $this->changes->jsonUnserialize($changes);

        if (!is_array($preferredInstallChanged = $packageConfig[self::PREFERRED_INSTALL_CHANGED] ?? null)) {
            $preferredInstallChanged = [];
        }

        $this->preferredInstallChanged->jsonUnserialize($preferredInstallChanged);

        return $this;
    }

    public function save(JsonConfigSource $jsonConfigSource): self
    {
        if (
            $this->changes->isEmpty()
            && $this->preferredInstallChanged->isEmpty()
        ) {
            $jsonConfigSource->removeProperty(
                sprintf(
                    '%s.%s',
                    self::EXTRA,
                    self::PACKAGE
                )
            );

            return $this;
        }

        if (!$this->changes->isEmpty()) {
            $jsonConfigSource->addProperty(
                sprintf(
                    '%s.%s.%s',
                    self::EXTRA,
                    self::PACKAGE,
                    self::CHANGES
                ),
                $this->changes->jsonSerialize()
            );
        } else {
            $jsonConfigSource->removeProperty(
                sprintf(
                    '%s.%s.%s',
                    self::EXTRA,
                    self::PACKAGE,
                    self::CHANGES
                )
            );
        }

        if (!$this->preferredInstallChanged->isEmpty()) {
            $jsonConfigSource->addProperty(
                sprintf(
                    '%s.%s.%s',
                    self::EXTRA,
                    self::PACKAGE,
                    self::PREFERRED_INSTALL_CHANGED
                ),
                $this->preferredInstallChanged->jsonSerialize()
            );
        } else {
            $jsonConfigSource->removeProperty(
                sprintf(
                    '%s.%s.%s',
                    self::EXTRA,
                    self::PACKAGE,
                    self::PREFERRED_INSTALL_CHANGED
                )
            );
        }

        return $this;
    }
}
