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

namespace GsTYPO3\CorePatches\Tests\Unit;

use Composer\Composer;
use Composer\Config as ComposerConfig;
use Composer\Config\ConfigSourceInterface;
use Composer\DependencyResolver\Operation\InstallOperation;
use Composer\DependencyResolver\Operation\UpdateOperation;
use Composer\DependencyResolver\Transaction;
use Composer\EventDispatcher\EventSubscriberInterface;
use Composer\Installer\InstallerEvent;
use Composer\Installer\InstallerEvents;
use Composer\IO\IOInterface;
use Composer\Json\JsonFile;
use Composer\Package\PackageInterface;
use Composer\Plugin\Capability\CommandProvider as ComposerCommandProvider;
use Composer\Plugin\Capable;
use Composer\Plugin\PluginInterface;
use Composer\Script\Event;
use Composer\Script\ScriptEvents;
use GsTYPO3\CorePatches\CommandProvider;
use GsTYPO3\CorePatches\Config;
use GsTYPO3\CorePatches\Plugin;
use GsTYPO3\CorePatches\Utility\ComposerUtils;
use Prophecy\Argument;
use stdClass;

/**
 * @covers \GsTYPO3\CorePatches\Plugin
 */
final class PluginTest extends TestCase
{
    /**
     * @small
     */
    public function testPluginInterfaces(): void
    {
        /** @var stdClass $plugin */
        $plugin = new Plugin();

        self::assertInstanceOf(PluginInterface::class, $plugin);
        self::assertInstanceOf(Capable::class, $plugin);
        self::assertInstanceOf(EventSubscriberInterface::class, $plugin);
    }

    /**
     * @medium
     */
    public function testActivate(): void
    {
        $configSourceProphecy = $this->prophesize(ConfigSourceInterface::class);
        $configSourceProphecy->addConfigSetting('allow-plugins.cweagans/composer-patches', true)
            ->shouldBeCalled()
        ;

        $composerConfigProphecy = $this->prophesize(ComposerConfig::class);
        $composerConfigProphecy->get('disable-tls')
            ->willReturn(\false)
            ->shouldBeCalled()
        ;
        $composerConfigProphecy->get('cafile')
            ->willReturn('')
            ->shouldBeCalled()
        ;
        $composerConfigProphecy->get('capath')
            ->willReturn('')
            ->shouldBeCalled()
        ;
        $composerConfigProphecy->getConfigSource()
            ->willReturn($configSourceProphecy)
            ->shouldBeCalled()
        ;

        $composerProphecy = $this->prophesize(Composer::class);
        $composerProphecy->getConfig()
            ->willReturn($composerConfigProphecy->reveal())
            ->shouldBeCalled()
        ;

        $ioProphecy = $this->prophesize(IOInterface::class);

        $composerUtilsProphecy = $this->prophesize(ComposerUtils::class);

        $jsonFileProphecy = $this->prophesize(JsonFile::class);

        $config = new Config($jsonFileProphecy->reveal(), $configSourceProphecy->reveal());

        (new Plugin())
            ->activate($composerProphecy->reveal(), $ioProphecy->reveal())
        ;

        (new Plugin($composerUtilsProphecy->reveal(), $config))
            ->activate($composerProphecy->reveal(), $ioProphecy->reveal())
        ;
    }

    /**
     * @medium
     * @doesNotPerformAssertions
     */
    public function testDeactivate(): void
    {
        $composerProphecy = $this->prophesize(Composer::class);
        $ioProphecy = $this->prophesize(IOInterface::class);

        (new Plugin())->deactivate($composerProphecy->reveal(), $ioProphecy->reveal());
    }

    /**
     * @medium
     * @doesNotPerformAssertions
     */
    public function testUninstall(): void
    {
        $composerProphecy = $this->prophesize(Composer::class);
        $ioProphecy = $this->prophesize(IOInterface::class);

        (new Plugin())->uninstall($composerProphecy->reveal(), $ioProphecy->reveal());
    }

    /**
     * @medium
     */
    public function testGetCapabilities(): void
    {
        self::assertSame(
            [ComposerCommandProvider::class => CommandProvider::class],
            (new Plugin())->getCapabilities()
        );
    }

    /**
     * @medium
     */
    public function testGetSubscribedEvents(): void
    {
        self::assertSame(
            [
                InstallerEvents::PRE_OPERATIONS_EXEC => ['checkForOutdatedPatches', 50],
                ScriptEvents::POST_INSTALL_CMD => ['removeOutdatedPatches', 50],
                ScriptEvents::POST_UPDATE_CMD => ['removeOutdatedPatches', 50],
            ],
            Plugin::getSubscribedEvents()
        );
    }

    /**
     * @medium
     */
    public function testCheckForOutdatedPatches(): void
    {
        $composerUtilsProphecy = $this->prophesize(ComposerUtils::class);
        $composerUtilsProphecy->truncateOutdatedPatchesForPackage(Argument::type(PackageInterface::class))
            ->willReturn([])
            ->shouldBeCalled()
        ;

        $jsonFileProphecy = $this->prophesize(JsonFile::class);
        $configSourceProphecy = $this->prophesize(ConfigSourceInterface::class);

        $config = new Config($jsonFileProphecy->reveal(), $configSourceProphecy->reveal());

        $packageProphecy = $this->prophesize(PackageInterface::class);

        $installOperationProphecy = $this->prophesize(InstallOperation::class);
        $installOperationProphecy->getPackage()
            ->willReturn($packageProphecy->reveal())
            ->shouldBeCalled()
        ;

        $updateOperationProphecy = $this->prophesize(UpdateOperation::class);
        $updateOperationProphecy->getTargetPackage()
            ->willReturn($packageProphecy->reveal())
            ->shouldBeCalled()
        ;

        $transactionProphecy = $this->prophesize(Transaction::class);
        $transactionProphecy->getOperations()
            ->willReturn([
                $installOperationProphecy->reveal(),
                $updateOperationProphecy->reveal(),
            ])
            ->shouldBeCalled()
        ;

        $output = '';

        $ioProphecy = $this->prophesize(IOInterface::class);
        $ioProphecy->write(Argument::type('string'))->will(
            static function (array $args) use (&$output): void {
                $output .= $args[0];
            }
        );

        $installerEventProphecy = $this->prophesize(InstallerEvent::class);
        $installerEventProphecy->getIO()
            ->willReturn($ioProphecy->reveal())
            ->shouldBeCalled()
        ;

        // Early exit without ComposerUtils
        (new Plugin())
            ->checkForOutdatedPatches($installerEventProphecy->reveal())
        ;

        // Early exit without Config
        (new Plugin($composerUtilsProphecy->reveal()))
            ->checkForOutdatedPatches($installerEventProphecy->reveal())
        ;

        // Early exit without transaction
        $installerEventProphecy->getTransaction()
            ->willReturn(\null)
            ->shouldBeCalled()
        ;

        (new Plugin($composerUtilsProphecy->reveal(), $config))
            ->checkForOutdatedPatches($installerEventProphecy->reveal())
        ;

        self::assertStringNotContainsString(
            '<info>Checking for outdated patches, this may take a while...</info>',
            $output
        );

        // Early exit if CI
        $installerEventProphecy->getTransaction()
            ->willReturn($transactionProphecy->reveal())
            ->shouldBeCalled()
        ;
        $ciBackup = \getenv('GS_CI');
        \putenv('GS_CI=1');

        (new Plugin($composerUtilsProphecy->reveal(), $config))
            ->checkForOutdatedPatches($installerEventProphecy->reveal())
        ;

        self::assertStringNotContainsString(
            '<info>Checking for outdated patches, this may take a while...</info>',
            $output
        );

        // Early exit if disable tidy patches
        /*
        $output = '';

        \putenv('GS_CI=');

        (new Plugin($composerUtilsProphecy->reveal(), $config))
            ->checkForOutdatedPatches($installerEventProphecy->reveal())
        ;

        self::assertStringNotContainsString(
            '<info>Checking for outdated patches, this may take a while...</info>',
            $output
        );
        */

        // Normal processing
        \putenv('GS_CI=' . $ciBackup);

        (new Plugin($composerUtilsProphecy->reveal(), $config))
            ->checkForOutdatedPatches($installerEventProphecy->reveal())
        ;

        self::assertStringContainsString(
            '<info>Checking for outdated patches, this may take a while...</info>',
            $output
        );
    }

    /**
     * @medium
     */
    public function testRemoveOutdatedPatches(): void
    {
        $composerUtilsProphecy = $this->prophesize(ComposerUtils::class);
        $composerUtilsProphecy->removePatches(Argument::type('array'), \true)
            ->willReturn(5)
            ->shouldBeCalled()
        ;

        $jsonFileProphecy = $this->prophesize(JsonFile::class);
        $configSourceProphecy = $this->prophesize(ConfigSourceInterface::class);

        $config = new Config($jsonFileProphecy->reveal(), $configSourceProphecy->reveal());

        $output = '';

        $ioProphecy = $this->prophesize(IOInterface::class);
        $ioProphecy->write(Argument::type('string'))->will(
            static function (array $args) use (&$output): void {
                $output .= $args[0];
            }
        );

        $eventProphecy = $this->prophesize(Event::class);
        $eventProphecy->getIO()
            ->willReturn($ioProphecy->reveal())
            ->shouldBeCalled()
        ;

        // Early exit without ComposerUtils
        (new Plugin())
            ->removeOutdatedPatches($eventProphecy->reveal())
        ;

        // Normal processing
        (new Plugin($composerUtilsProphecy->reveal(), $config, ['12345']))
            ->removeOutdatedPatches($eventProphecy->reveal())
        ;

        self::assertStringContainsString(
            '<info>Removing patches marked for removal...</info>',
            $output
        );
        self::assertStringContainsString(
            '<info>5 patches successfully removed.</info>',
            $output
        );
    }
}
