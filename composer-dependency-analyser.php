<?php

declare(strict_types=1);

use ShipMonk\ComposerDependencyAnalyser\Config\Configuration;
use ShipMonk\ComposerDependencyAnalyser\Config\ErrorType;

$configuration = new Configuration;

return $configuration
    ->ignoreErrorsOnPackage('laravel/chisel', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('laravel/tinker', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('laravel/telescope', [ErrorType::DEV_DEPENDENCY_IN_PROD])
    ->ignoreErrorsOnPackage('laravel/wayfinder', [ErrorType::UNUSED_DEPENDENCY])
    ->ignoreErrorsOnPackage('nesbot/carbon', [ErrorType::SHADOW_DEPENDENCY])
    ->ignoreErrorsOnPackage('pestphp/pest-plugin-arch', [ErrorType::SHADOW_DEPENDENCY])
    ->ignoreErrorsOnPackage('symfony/http-foundation', [ErrorType::SHADOW_DEPENDENCY]);
