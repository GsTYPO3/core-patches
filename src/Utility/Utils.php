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

final class Utils
{
    /**
     * Detects if running in CI.
     *
     * based on https://github.com/watson/ci-info/blob/HEAD/index.js
     */
    public static function isCI(): bool
    {
        return (
            // GitHub Actions, Travis CI, CircleCI, Cirrus CI, GitLab CI, AppVeyor, CodeShip, dsari
            \getenv('CI') !== false ||
            // Jenkins, TeamCity
            \getenv('BUILD_NUMBER') !== false ||
            // TaskCluster, dsari
            \getenv('RUN_ID') !== false ||
            // For testing
            \getenv('GS_CI') === '1'
        ) && \getenv('GS_CI') !== '0';
    }
}
