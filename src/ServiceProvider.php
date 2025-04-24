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
        $this->app->extend(StaticCacheManager::class, function (StaticCacheManager $staticCacheManager, $app) {
            // We need to add the createFileDriver method to the manager
            $staticCacheManager->extend('file', function ($app, array $config) {
                $permissions = isset($config['permissions']) && is_array($config['permissions'])
                    ? $config['permissions']
                    : [];

                return new CacheBusterFileCacher(
                    new Writer($permissions),
                    Cache::store($this->hasCustomStore() ? 'static_cache' : null),
                    $config
                );
            });

            return $staticCacheManager;
        });
    }

    private function hasCustomStore(): bool
    {
        return config()->has('cache.stores.static_cache');
    }
}
