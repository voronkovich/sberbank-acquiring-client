<?php

$finder = PhpCsFixer\Finder::create()
    ->in('src')
;

return (new PhpCsFixer\Config())
    ->setRules([
        '@PSR2' => true,
        'array_syntax' => ['syntax' => 'short'],
    ])
    ->setFinder($finder)
    ->setUsingCache(false)
;
