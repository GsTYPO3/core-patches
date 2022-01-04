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

namespace GsTYPO3\CorePatches\Utility;

use Composer\Composer;
use Composer\IO\IOInterface;
use Composer\Semver\Constraint\MatchAllConstraint;
use UnexpectedValueException;

final class PatchCreator
{
    /** @var Composer */
    private $composer;
    /** @var IOInterface */
    private $io;

    public function __construct(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    /**
     * @param int       $numericId      The numeric ID.
     * @param string    $subject        The subject of the patch.
     * @param string    $patch          The raw patch.
     * @param string    $destination    The destination relative to the composer root folder. Defaults to 'patches'.
     * @param bool      $includeTests   If set to true changes in test files are skiped. Defaults to false.
     * @return array<string, array<string, string>>    The patch split to core packages.
     * @throws UnexpectedValueException
     */
    public function create(
        int $numericId,
        string $subject,
        string $patch,
        string $destination = 'patches',
        bool $includeTests = false
    ): array {
        return $this->save(
            $destination,
            $numericId,
            $subject,
            $this->split($patch, $includeTests)
        );
    }

    /**
     * @param string    $patch          The raw patch.
     * @param bool      $includeTests   If set to true changes in test files are also included. Defaults to false.
     * @return array<string, array<int, string>>    The patch split into core packages.
     * @throws UnexpectedValueException
     */
    public function split(string $patch, bool $includeTests): array
    {
        // Extract patch message
        if (($nextPatchPos = strpos($patch, 'diff --git')) === false) {
            throw new UnexpectedValueException('Patch message end not found.', 1640955563);
        }

        $patchMessage = substr($patch, 0, $nextPatchPos);
        $patch = substr($patch, $nextPatchPos);

        // Split the patch from the mono repository into individual packages
        $patches = [];

        while ($patch !== '') {
            $nextPatchPos = strpos($patch, 'diff --git', 1);
            if ($nextPatchPos === false) {
                $buffer = $patch;
                $patch = '';
            } else {
                $buffer = substr($patch, 0, $nextPatchPos);
                $patch = substr($patch, $nextPatchPos);
            }

            // Skip tests if including not requested
            if (preg_match('#^diff --git a/typo3/sysext/([^/]+)/Tests/#', $buffer, $matches) === 1 && !$includeTests) {
                $this->io->write(sprintf('  - Skipping tests for <info>typo3/cms-%s</info>', $matches[1]));
                continue;
            }

            // Lookup core package name and file name, skip other files
            if (preg_match('#^diff --git a/typo3/sysext/([^/]+)/([^ ]+)#', $buffer, $matches) === false) {
                $this->io->write(sprintf('  - Skipping file <info>%s</info>', $matches[2]));
                continue;
            }

            $sysext = $matches[1];
            $packageName = 'typo3/cms-' . str_replace('_', '-', $sysext);

            // Skip not installed packages
            $package = $this->composer->getRepositoryManager()->getLocalRepository()->findPackage(
                $packageName,
                new MatchAllConstraint()
            );

            if ($package === null) {
                $this->io->write(sprintf('  - Skipping package <info>%s</info>', $packageName));
                continue;
            }

            // Set patch message once for each patch file
            if (!isset($patches[$packageName])) {
                $patches[$packageName] = [$patchMessage];
            }

            // Change file names from mono repository to split package
            $prefix = 'typo3/sysext/' . $matches[1];
            $file = $matches[2];
            $buffer = str_replace(' a/' . $prefix . '/' . $file, ' a/' . $file, $buffer);
            $buffer = str_replace(' b/' . $prefix . '/' . $file, ' b/' . $file, $buffer);

            $patches[$packageName][] = $buffer;
        }

        return $patches;
    }

    /**
     * @param string                            $destination    The destination relative to the composer root folder.
     * @param int                               $numericId      The numeric ID.
     * @param string                            $subject        The subject.
     * @param array<string, array<int, string>> $patches
     * @return array<string, array<string, string>>    The patch split to core packages.
     * @throws UnexpectedValueException
     */
    public function save(string $destination, int $numericId, string $subject, array $patches): array
    {
        if (!file_exists($destination)) {
            mkdir($destination, 0777, true);
        }

        $composerChanges = [];

        foreach ($patches as $packageName => $chunks) {
            $content = implode('', $chunks);
            $patchFileName = $destination . '/' . $numericId . '-' . str_replace('/', '-', $packageName) . '.patch';
            $this->io->write(sprintf('  - Creating patch <info>%s</info>', $patchFileName));
            file_put_contents($patchFileName, $content);
            $composerChanges[$packageName] = [$subject => $patchFileName];
        }

        return $composerChanges;
    }
}
