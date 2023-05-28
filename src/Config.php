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

use Composer\Config\ConfigSourceInterface;
use Composer\Config\JsonConfigSource;
use Composer\Factory;
use Composer\Json\JsonFile;
use GsTYPO3\CorePatches\Config\Changes;
use GsTYPO3\CorePatches\Config\Patches;
use GsTYPO3\CorePatches\Config\PersistenceInterface;
use GsTYPO3\CorePatches\Config\PreferredInstall;
use GsTYPO3\CorePatches\Config\PreferredInstallChanged;

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
    private const PLUGIN_CHANGES = 'applied-changes';

    /**
     * @var string
     */
    private const PLUGIN_PREFERRED_INSTALL_CHANGED = 'preferred-install-changed';

    /**
     * @var string
     */
    private const PLUGIN_PATCH_DIRECTORY = 'patch-directory';

    /**
     * @var string
     */
    private const DEFAULT_PATCH_DIRECTORY = 'patches';

    /**
     * @var string
     */
    private const PLUGIN_IGNORE_BRANCH = 'ignore-branch';

    /**
     * @var string
     */
    private const PLUGIN_DISABLE_TIDY_PATCHES = 'disable-tidy-patches';

    /**
     * @var string
     */
    private const PLUGIN_FORCE_TIDY_PATCHES = 'force-tidy-patches';

    private JsonFile $jsonFile;

    private ConfigSourceInterface $configSource;

    private Changes $changes;

    private PreferredInstallChanged $preferredInstallChanged;

    private string $patchDirectory = '';

    private PreferredInstall $preferredInstall;

    private Patches $patches;

    private bool $ignoreBranch = \false;

    private bool $disableTidyPatches = \false;

    private bool $forceTidyPatches = \false;

    public function __construct(
        ?JsonFile $jsonFile = null,
        ?ConfigSourceInterface $configSource = null
    ) {
        $this->jsonFile = $jsonFile ?? new JsonFile(Factory::getComposerFile());
        $this->configSource = $configSource ?? new JsonConfigSource($this->jsonFile);

        $this->changes = new Changes();
        $this->preferredInstallChanged = new PreferredInstallChanged();
        $this->preferredInstall = new PreferredInstall();
        $this->patches = new Patches();
    }

    public function getChanges(): Changes
    {
        return $this->changes;
    }

    public function getPreferredInstallChanged(): PreferredInstallChanged
    {
        return $this->preferredInstallChanged;
    }

    public function getPatchDirectory(): string
    {
        if ($this->patchDirectory === '') {
            return self::DEFAULT_PATCH_DIRECTORY;
        }

        return $this->patchDirectory;
    }

    public function setPatchDirectory(string $patchDirectory): self
    {
        $this->patchDirectory = $patchDirectory;

        return $this;
    }

    public function getIgnoreBranch(): bool
    {
        return $this->ignoreBranch;
    }

    public function setIgnoreBranch(bool $ignoreBranch): self
    {
        $this->ignoreBranch = $ignoreBranch;

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

    public function getDisableTidyPatches(): bool
    {
        return $this->disableTidyPatches;
    }

    public function setDisableTidyPatches(bool $disableTidyPatches): self
    {
        $this->disableTidyPatches = $disableTidyPatches;

        return $this;
    }

    public function getForceTidyPatches(): bool
    {
        return $this->forceTidyPatches;
    }

    public function setForceTidyPatches(bool $forceTidyPatches): self
    {
        $this->forceTidyPatches = $forceTidyPatches;

        return $this;
    }

    private function isEmpty(): bool
    {
        if (!$this->changes->isEmpty()) {
            return false;
        }

        if (!$this->preferredInstallChanged->isEmpty()) {
            return false;
        }

        if ($this->patchDirectory !== '') {
            return false;
        }

        if ($this->ignoreBranch) {
            return false;
        }

        if ($this->disableTidyPatches) {
            return false;
        }

        return !$this->forceTidyPatches;
    }

    public function load(): self
    {
        if (!is_array($config = $this->jsonFile->read())) {
            $config = [];
        }

        $this->jsonUnserialize($config);

        return $this;
    }

    public function save(): self
    {
        // Save preferred-install
        foreach ($this->preferredInstall as $packageName => $installMethod) {
            $name = sprintf(
                '%s.%s',
                self::CONFIG_PREFERRED_INSTALL,
                $packageName
            );

            if ($installMethod === '') {
                $this->configSource->removeConfigSetting($name);
            } else {
                $this->configSource->addConfigSetting($name, $installMethod);
            }
        }

        // Save patches
        $name = sprintf(
            '%s.%s',
            self::EXTRA,
            self::EXTRA_PATCHES
        );

        if ($this->patches->isEmpty()) {
            $this->configSource->removeProperty($name);
        } else {
            $this->configSource->addProperty($name, $this->patches);
        }

        // Save plugin configuration
        $name = sprintf(
            '%s.%s',
            self::EXTRA,
            self::EXTRA_PLUGIN
        );

        if ($this->isEmpty()) {
            $this->configSource->removeProperty($name);
        } else {
            $this->configSource->addProperty($name, $this);
        }

        // Rewrite configuration to enforce correct formatting
        if (is_array($rawConfig = $this->jsonFile->read())) {
            $this->jsonFile->write($rawConfig);
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
            $config[self::PLUGIN_CHANGES] = $this->changes;
        }

        if (!$this->preferredInstallChanged->isEmpty()) {
            $config[self::PLUGIN_PREFERRED_INSTALL_CHANGED] = $this->preferredInstallChanged;
        }

        if ($this->patchDirectory !== '' && $this->patchDirectory !== self::DEFAULT_PATCH_DIRECTORY) {
            $config[self::PLUGIN_PATCH_DIRECTORY] = $this->patchDirectory;
        }

        if ($this->ignoreBranch) {
            $config[self::PLUGIN_IGNORE_BRANCH] = $this->ignoreBranch;
        }

        if ($this->disableTidyPatches) {
            $config[self::PLUGIN_DISABLE_TIDY_PATCHES] = $this->disableTidyPatches;
        }

        if ($this->forceTidyPatches) {
            $config[self::PLUGIN_FORCE_TIDY_PATCHES] = $this->forceTidyPatches;
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

        if (!is_array($changes = $packageConfig[self::PLUGIN_CHANGES] ?? null)) {
            $changes = [];
        }

        $this->changes->jsonUnserialize($changes);

        if (!is_array($preferredInstallChanged = $packageConfig[self::PLUGIN_PREFERRED_INSTALL_CHANGED] ?? null)) {
            $preferredInstallChanged = [];
        }

        $this->preferredInstallChanged->jsonUnserialize($preferredInstallChanged);

        if (!is_string($patchDirectory = $packageConfig[self::PLUGIN_PATCH_DIRECTORY] ?? null)) {
            $patchDirectory = '';
        }

        $this->patchDirectory = $patchDirectory;

        if (!is_bool($ignoreBranch = $packageConfig[self::PLUGIN_IGNORE_BRANCH] ?? null)) {
            $ignoreBranch = false;
        }

        $this->ignoreBranch = $ignoreBranch;

        if (!is_bool($disableTidyPatches = $packageConfig[self::PLUGIN_DISABLE_TIDY_PATCHES] ?? null)) {
            $disableTidyPatches = false;
        }

        $this->disableTidyPatches = $disableTidyPatches;

        if (!is_bool($forceTidyPatches = $packageConfig[self::PLUGIN_FORCE_TIDY_PATCHES] ?? null)) {
            $forceTidyPatches = false;
        }

        $this->forceTidyPatches = $forceTidyPatches;

        return $this;
    }
}
