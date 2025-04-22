<?php

declare(strict_types=1);

namespace FRoepstorf\StaticCacheBuster\Tests;

use FRoepstorf\StaticCacheBuster\StaticCaching\CacheBusterFileCacher;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Orchestra\Testbench\TestCase;
use Override;
use Statamic\StaticCaching\Cachers\Writer;

class CacheBusterTest extends TestCase
{
    /**
     * @var Writer
     */
    protected $writerMock;

    /**
     * @var Repository
     */
    protected $cache;

    /**
     * @var CacheBusterFileCacher
     */
    protected $cacher;

    #[Override]
    protected function setUp(): void
    {
        parent::setUp();

        $this->writerMock = $this->createMock(Writer::class);
        $this->cache = app('cache')->store();

        $this->cacher = new CacheBusterFileCacher(
            $this->writerMock,
            $this->cache,
            ['path' => storage_path('framework/testing/static-cache')]
        );

        File::shouldReceive('exists')->andReturn(true);
    }

    /** @test */
    public function it_returns_true_for_normal_requests()
    {
        $request = Request::create('http://example.com/test-page');

        // Assert that hasCachedPage returns true (our mock is set to return true)
        $this->assertTrue($this->cacher->hasCachedPage($request));
    }

    /** @test */
    public function it_returns_false_for_requests_with_cache_buster_header()
    {
        $request = Request::create('http://example.com/test-page');
        $request->headers->set('X-Statamic-Cache-Buster', 'true');

        // Assert that hasCachedPage returns false despite the file existing
        $this->assertFalse($this->cacher->hasCachedPage($request));
    }
}
