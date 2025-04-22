<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;
use Rector\TypeDeclaration\Rector\StmtsAwareInterface\DeclareStrictTypesRector;
use RectorLaravel\Set\LaravelLevelSetList;

return RectorConfig::configure()
    ->withPaths([__DIR__.'/src', __DIR__.'/tests'])
    ->withRules([
        DeclareStrictTypesRector::class,
    ])
    ->withSets([
        LevelSetList::UP_TO_PHP_83,
        LaravelLevelSetList::UP_TO_LARAVEL_110,
        SetList::PHP_83,
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
        SetList::NAMING,
        SetList::EARLY_RETURN,
    ])
    ->withImportNames(removeUnusedImports: true);
