<?php

$finder = PhpCsFixer\Finder::create()
    ->in([__DIR__ . "/library", __DIR__ . "/listeners"]);

$config = new PhpCsFixer\Config();
return $config->setRules([
        '@Symfony' => true,
    ])
    ->setFinder($finder)
;