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
use Composer\Package\BasePackage;
use Composer\Semver\Constraint\MatchAllConstraint;
use GsTYPO3\CorePatches\Config\Patches;
use GsTYPO3\CorePatches\Exception\InvalidPatchException;
use GsTYPO3\CorePatches\Exception\NoPatchException;

final class PatchUtils
{
    private Composer $composer;

    private IOInterface $io;

    public function __construct(Composer $composer, IOInterface $io)
    {
        $this->composer = $composer;
        $this->io = $io;
    }

    public function getPatchFileName(string $destination, string $packageName, int $numericId): string
    {
        return sprintf(
            '%s/%s-review-%s.patch',
            $destination,
            str_replace('/', '-', $packageName),
            $numericId
        );
    }

    public function patchIsPartOfChange(string $patchFileName, int $numericId): bool
    {
        return strpos($patchFileName, '-review-' . $numericId . '.patch') !== false;
    }

    /**
     * @param  int                                  $numericId    the numeric ID
     * @param  string                               $subject      the subject of the patch
     * @param  string                               $patch        the raw patch
     * @param  string                               $destination  the destination relative to the composer root folder,
     *                                                            defaults to 'patches'
     * @param  bool                                 $includeTests if set to true changes in test files are skiped,
     *                                                            defaults to false
     * @return array<string, array<string, string>> the patches split to core packages
     * @throws InvalidPatchException
     * @throws NoPatchException
     */
    public function create(
        int $numericId,
        string $subject,
        string $patch,
        string $destination,
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
     * @param  array<int, int> $numericIds the numeric IDs of the patches to remove
     * @param  Patches         $patches    the available patches
     * @return Patches         the removed patches
     */
    public function remove(array $numericIds, Patches $patches): Patches
    {
        $patchesRemoved = new Patches();

        foreach ($numericIds as $numericId) {
            foreach ($patches as $packageName => $packagePatches) {
                foreach ($packagePatches as $subject => $patchFileName) {
                    if ($this->patchIsPartOfChange($patchFileName, $numericId)) {
                        $this->io->write(sprintf('  - Removing patch <info>%s</info>', $patchFileName));

                        $patchesRemoved->add($packageName, [$subject => $patchFileName]);

                        unlink($patchFileName);
                    }
                }
            }
        }

        return $patchesRemoved;
    }

    /**
     * @param  array<int, int> $numericIds the numeric IDs of the patches to remove
     * @param  Patches         $patches    the available patches
     * @return Patches         the removed patches
     */
    public function prepareRemove(array $numericIds, Patches $patches): Patches
    {
        $patchesRemoved = new Patches();

        foreach ($numericIds as $numericId) {
            foreach ($patches as $packageName => $packagePatches) {
                foreach ($packagePatches as $subject => $patchFileName) {
                    if ($this->patchIsPartOfChange($patchFileName, $numericId)) {
                        $this->io->write(sprintf('  - Removing patch <info>%s</info>', $patchFileName));

                        $patchesRemoved->add($packageName, [$subject => $patchFileName]);

                        file_put_contents(
                            $patchFileName,
                            ''
                        );
                    }
                }
            }
        }

        return $patchesRemoved;
    }

    /**
     * @param  string                            $patch        the raw patch
     * @param  bool                              $includeTests if set to true changes in test files are also included,
     *                                                         defaults to false
     * @return array<string, array<int, string>> the patch split into core packages
     * @throws InvalidPatchException
     */
    public function split(string $patch, bool $includeTests): array
    {
        // Extract patch message
        if (($nextPatchPos = strpos($patch, 'diff --git')) === false) {
            throw new InvalidPatchException('End of patch message not found.');
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
            if (preg_match('#^diff --git a/typo3/sysext/([^/]+)/([^ ]+)#', $buffer, $matches) !== 1) {
                $fileName = preg_match('#^diff --git a/([^ ]+)#', $buffer, $matches) === 1 ? $matches[1] : 'unknown';

                $this->io->write(sprintf('  - Skipping file <info>%s</info>', $fileName));
                continue;
            }

            $sysext = $matches[1];
            $packageName = 'typo3/cms-' . str_replace('_', '-', $sysext);

            // Skip not installed packages
            $package = $this->composer->getRepositoryManager()->getLocalRepository()->findPackage(
                $packageName,
                new MatchAllConstraint()
            );

            if (!$package instanceof BasePackage) {
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
     * @param  string                               $destination the destination relative to the composer root folder
     * @param  int                                  $numericId   the numeric ID
     * @param  string                               $subject     the subject
     * @param  array<string, array<int, string>>    $patches
     * @return array<string, array<string, string>> the patch split to core packages
     * @throws NoPatchException
     */
    public function save(
        string $destination,
        int $numericId,
        string $subject,
        array $patches
    ): array {
        if ($patches === []) {
            throw new NoPatchException('No patches provided to save.');
        }

        if (!file_exists($destination)) {
            mkdir($destination, 0777, true);
        }

        $composerChanges = [];

        foreach ($patches as $packageName => $chunks) {
            $content = implode('', $chunks);
            $patchFileName = $this->getPatchFileName($destination, $packageName, $numericId);
            $this->io->write(sprintf('  - Creating patch <info>%s</info>', $patchFileName));
            file_put_contents($patchFileName, $content);
            $composerChanges[$packageName] = [$subject => $patchFileName];
        }

        return $composerChanges;
    }
}
