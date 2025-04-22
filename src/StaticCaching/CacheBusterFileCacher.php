<?php

declare(strict_types=1);

namespace FRoepstorf\StaticCacheBuster\StaticCaching;

use Illuminate\Http\Request;
use Override;
use Statamic\StaticCaching\Cachers\FileCacher;

class CacheBusterFileCacher extends FileCacher
{
    /**
     * Check if a page has been cached, but bypass cache when the cache buster header is present.
     *
     * @return bool
     */
    #[Override]
    public function hasCachedPage(Request $request)
    {
        // Skip serving from cache when cache buster command is running
        if ($request->header('X-Statamic-Cache-Buster') === 'true') {
            return false;
        }

        return parent::hasCachedPage($request);
    }
}
