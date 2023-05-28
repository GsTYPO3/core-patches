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

use Composer\Composer;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\DependencyResolver\Transaction;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Factory;
use Composer\Installer\InstallerEvent;
use Composer\Installer\InstallerEvents;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Plugin\Capability\CommandProvider as ComposerCommandProvider;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use GsTYPO3\CorePatches\Utility\ComposerUtils;
use GsTYPO3\CorePatches\Utility\Utils;

final class Plugin implements PluginInterface, Capable, EventSubscriberInterface
{
    private ComposerUtils $composerUtils;

    private Config $config;

    /**
     * @var array<int, string>
     */
    private array $patchesToRemove = [];

    /**
     * @inheritDoc
     */
    public function activate(Composer $composer, IOInterface $io): void
    {
        $this->composerUtils = new ComposerUtils($composer, $io);
        $this->config = new Config(
            new JsonFile(Factory::getComposerFile(), Factory::createHttpDownloader($io, $composer->getConfig()), $io),
            $composer->getConfig()->getConfigSource()
        );

        $composer->getConfig()->getConfigSource()->addConfigSetting('allow-plugins.cweagans/composer-patches', true);
    }

    /**
     * @inheritDoc
     */
    public function deactivate(Composer $composer, IOInterface $io): void
    {
    }

    /**
     * @inheritDoc
     */
    public function uninstall(Composer $composer, IOInterface $io): void
    {
    }

    /**
     * @inheritDoc
     *
     * @return array<class-string<ComposerCommandProvider>, class-string<CommandProvider>>
     */
    public function getCapabilities(): array
    {
        return [
            ComposerCommandProvider::class => CommandProvider::class,
        ];
    }

    /**
     * @inheritDoc
     *
     * @return array{
     *     pre-operations-exec: int[]|string[],
     *     post-install-cmd: int[]|string[],
     *     post-update-cmd: int[]|string[]
     * }
     */
    public static function getSubscribedEvents(): array
    {
        return [
            InstallerEvents::PRE_OPERATIONS_EXEC => ['checkForObsoletePatches', 50],
            ScriptEvents::POST_INSTALL_CMD => ['removeOfObsoletePatches', 50],
            ScriptEvents::POST_UPDATE_CMD => ['removeOfObsoletePatches', 50],
        ];
    }

    /**
     * Check for obsolete patches before they get applied.
     */
    public function checkForObsoletePatches(InstallerEvent $installerEvent): void
    {
        if (!$installerEvent->getTransaction() instanceof Transaction) {
            return;
        }

        $this->config->load();

        if (Utils::isCI() && !$this->config->getForceTidyPatches()) {
            return;
        }

        if ($this->config->getDisableTidyPatches()) {
            return;
        }

        $installerEvent->getIO()->write('<info>Checking for obsolete patches, this may take a while...</info>');

        foreach ($installerEvent->getTransaction()->getOperations() as $operation) {
            if ($operation instanceof InstallOperation) {
                $this->patchesToRemove = [
                    ...$this->patchesToRemove,
                    ...$this->composerUtils->verifyPatchesForPackage($operation->getPackage()),
                ];
            } elseif ($operation instanceof UpdateOperation) {
                $this->patchesToRemove = [
                    ...$this->patchesToRemove,
                    ...$this->composerUtils->verifyPatchesForPackage($operation->getTargetPackage()),
                ];
            }
        }
    }

    /**
     * Check for obsolete patches before they get applied.
     */
    public function removeOfObsoletePatches(Event $event): void
    {
        if ($this->patchesToRemove !== []) {
            $event->getIO()->write('<info>Removing patches marked for removal...</info>');
            $this->composerUtils->removePatches($this->patchesToRemove, true);
            $event->getIO()->write('<info>Patches successfully removed.</info>');
        }
    }
}
