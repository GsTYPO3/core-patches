<?php

$header = <<<EOM
This file is part of TYPO3 Core Patches.

(c) Gilbertsoft LLC (gilbertsoft.org)

For the full copyright and license information, please view the LICENSE
file that was distributed with this source code.
EOM;

$config = \TYPO3\CodingStandards\CsFixerConfig::create();
$config
    ->setHeader($header, true)
    ->addRules([
        'modernize_strpos' => false,
        'phpdoc_align' => true,
        'phpdoc_annotation_without_dot' => true,
        'phpdoc_tag_casing' => true,
        'phpdoc_tag_type' => true,
    ])
    ->getFinder()
    ->exclude('tests/project/public')
    ->exclude('tests/project/vendor')
    ->exclude('vendor')
    ->in(__DIR__)
;

return $config;
