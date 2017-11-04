<?php declare(strict_types=1);

/**
 * This allows to load "vendor/autoload.php" both from
 * "composer create-project ..." and "composer require" installation.
 */
$possibleAutoloadPaths = [
    __DIR__ . '/../vendor/autoload.php', // repository
    __DIR__ . '/../vendor/autoload.php', // composer create-project
    __DIR__ . '/../../../../vendor/autoload.php', // composer require
];

foreach ($possibleAutoloadPaths as $possibleAutoloadPath) {
    if (is_file($possibleAutoloadPath)) {
        require_once $possibleAutoloadPath;

        break;
    }
}
