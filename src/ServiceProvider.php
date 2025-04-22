<?php

declare(strict_types=1);

namespace FRoepstorf\StaticCacheBuster;

use FRoepstorf\StaticCacheBuster\Commands\StaticCacheBusterCommand;
use FRoepstorf\StaticCacheBuster\StaticCaching\CacheBusterFileCacher;
use Illuminate\Support\Facades\Cache;
use Override;
use Statamic\Providers\AddonServiceProvider;
use Statamic\StaticCaching\Cachers\Writer;
use Statamic\StaticCaching\StaticCacheManager;

class ServiceProvider extends AddonServiceProvider
{
    protected $commands = [
        StaticCacheBusterCommand::class,
    ];

    #[Override]
    public function register()
    {
        parent::register();

        // Extend the StaticCacheManager to use our custom file cacher
        $this->app->extend(StaticCacheManager::class, function ($manager, $app) {
            // We need to add the createFileDriver method to the manager
            $manager->extend('file', fn ($app, $config) => new CacheBusterFileCacher(
                new Writer($config['permissions'] ?? []),
                Cache::store($this->hasCustomStore() ? 'static_cache' : null),
                $config
            ));

            return $manager;
        });
    }

    private function hasCustomStore(): bool
    {
        return config()->has('cache.stores.static_cache');
    }
}
