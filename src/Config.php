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
use Composer\Factory;
use Composer\Json\JsonFile;
use GsTYPO3\CorePatches\Config\Changes;
use GsTYPO3\CorePatches\Config\Packages;
use GsTYPO3\CorePatches\Config\Patches;
use GsTYPO3\CorePatches\Config\PersistenceInterface;
use GsTYPO3\CorePatches\Config\PreferredInstall;

final class Config implements PersistenceInterface
{
    /**
     * @var string
     */
    private const CONFIG = 'config';

    /**
     * @var string
     */
    private const CONFIG_PREFERRED_INSTALL = 'preferred-install';

    /**
     * @var string
     */
    private const EXTRA = 'extra';

    /**
     * @var string
     */
    private const EXTRA_PATCHES = 'patches';

    /**
     * @var string
     */
    private const EXTRA_PLUGIN = 'gilbertsoft/typo3-core-patches';

    /**
     * @var string
     */
    private const CHANGES = 'applied-changes';

    /**
     * @var string
     */
    private const PREFERRED_INSTALL_CHANGED = 'preferred-install-changed';

    /**
     * @var string
     */
    private const PATCH_DIRECTORY = 'patch-directory';

    private Changes $changes;

    private Packages $preferredInstallChanged;

    private string $patchDirectory = '';

    private PreferredInstall $preferredInstall;

    private Patches $patches;

    public function __construct()
    {
        $this->changes = new Changes($this);
        $this->preferredInstallChanged = new Packages($this);
        $this->preferredInstall = new PreferredInstall($this);
        $this->patches = new Patches($this);
    }

    public function getChanges(): Changes
    {
        return $this->changes;
    }

    public function getPreferredInstallChanged(): Packages
    {
        return $this->preferredInstallChanged;
    }

    public function getPatchDirectory(): string
    {
        if ($this->patchDirectory === '') {
            return 'patches';
        }

        return $this->patchDirectory;
    }

    public function setPatchDirectory(string $patchDirectory): self
    {
        $this->patchDirectory = $patchDirectory;

        return $this;
    }

    public function getPreferredInstall(): PreferredInstall
    {
        return $this->preferredInstall;
    }

    public function getPatches(): Patches
    {
        return $this->patches;
    }

    private function isEmpty(): bool
    {
        /** @noRector \Rector\EarlyReturn\Rector */
        return $this->changes->isEmpty()
            && $this->preferredInstallChanged->isEmpty()
            && $this->patchDirectory === ''
        ;
    }

    public function load(?JsonFile $jsonFile = null): self
    {
        if ($jsonFile === null) {
            $jsonFile = new JsonFile(Factory::getComposerFile());
        }

        if (!is_array($config = $jsonFile->read())) {
            $config = [];
        }

        $this->jsonUnserialize($config);

        return $this;
    }

    public function save(?JsonFile $jsonFile = null): self
    {
        if ($jsonFile === null) {
            $jsonFile = new JsonFile(Factory::getComposerFile());
        }

        $jsonConfigSource = new JsonConfigSource($jsonFile);

        // Save preferred-install
        foreach ($this->preferredInstall as $packageName => $installMethod) {
            $name = sprintf(
                '%s.%s',
                self::CONFIG_PREFERRED_INSTALL,
                $packageName
            );

            if ($installMethod === '') {
                $jsonConfigSource->removeConfigSetting($name);
            } else {
                $jsonConfigSource->addConfigSetting($name, $installMethod);
            }
        }

        // Save patches
        $name = sprintf(
            '%s.%s',
            self::EXTRA,
            self::EXTRA_PATCHES
        );

        if ($this->patches->isEmpty()) {
            $jsonConfigSource->removeProperty($name);
        } else {
            $jsonConfigSource->addProperty($name, $this->patches);
        }

        // Save plugin configuration
        $name = sprintf(
            '%s.%s',
            self::EXTRA,
            self::EXTRA_PLUGIN
        );

        if ($this->isEmpty()) {
            $jsonConfigSource->removeProperty($name);
        } else {
            $jsonConfigSource->addProperty($name, $this);
        }

        // Rewrite configuration to enforce correct formating
        if (is_array($rawConfig = $jsonFile->read())) {
            $jsonFile->write($rawConfig);
        }

        return $this;
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
        $config = [];

        if (!$this->changes->isEmpty()) {
            $config[self::CHANGES] = $this->changes;
        }

        if (!$this->preferredInstallChanged->isEmpty()) {
            $config[self::PREFERRED_INSTALL_CHANGED] = $this->preferredInstallChanged;
        }

        if ($this->patchDirectory !== '') {
            $config[self::PATCH_DIRECTORY] = $this->patchDirectory;
        }

        return $config;
    }

    /**
     * @inheritDoc
     */
    public function jsonUnserialize(array $json): self
    {
        if (!is_array($config = $json[self::CONFIG] ?? null)) {
            $config = [];
        }

        if (!is_array($extra = $json[self::EXTRA] ?? null)) {
            $extra = [];
        }

        // Load preferred-install
        if (!is_array($preferredInstall = $config[self::CONFIG_PREFERRED_INSTALL] ?? null)) {
            $preferredInstall = [];
        }

        $this->preferredInstall->jsonUnserialize($preferredInstall);

        // Load patches
        if (!is_array($patches = $extra[self::EXTRA_PATCHES] ?? null)) {
            $patches = [];
        }

        $this->patches->jsonUnserialize($patches);

        // Load plugin configuration
        if (!is_array($packageConfig = $extra[self::EXTRA_PLUGIN] ?? null)) {
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

        if (!is_string($patchDirectory = $packageConfig[self::PATCH_DIRECTORY] ?? null)) {
            $patchDirectory = '';
        }

        $this->patchDirectory = $patchDirectory;

        return $this;
    }
}
