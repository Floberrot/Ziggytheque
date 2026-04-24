<?php

use PhpCsFixer\Config;
use PhpCsFixer\Finder;

$finder = Finder::create()
    ->in(__DIR__ . '/src')
    ->in(__DIR__ . '/tests');

return (new Config())
    ->setRules([
        '@PSR12' => true,
        '@PHP84Migration' => true,
    ])
    ->setFinder($finder);
