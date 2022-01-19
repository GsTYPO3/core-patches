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

namespace GsTYPO3\CorePatches\Tests;

$file = __DIR__ . '/../vendor/autoload.php';

if (!file_exists($file)) {
    die('You must set up the project dependencies with Composer first.' . PHP_EOL);
}

include $file;
