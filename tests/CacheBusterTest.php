<?php

declare(strict_types=1);

namespace FRoepstorf\StaticCacheBuster\Tests;

use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

// Test helper class to isolate the method we want to test
class TestCacheBusterCacher
{
    /**
     * Check if a page has been cached, but bypass cache when the cache buster header is present.
     */
    public function hasCachedPage(Request $request): bool
    {
        // Skip serving from cache when cache buster command is running
        return $request->header('X-Statamic-Cache-Buster') !== 'true';
        // For testing, we always return true for non-cache-buster requests
    }
}

class CacheBusterTest extends TestCase
{
    private $cacher;

    protected function setUp(): void
    {
        parent::setUp();
        $this->cacher = new TestCacheBusterCacher;
    }

    #[Test]
    public function it_returns_true_for_normal_requests()
    {
        // Set up a normal request without the header
        $request = Request::create('http://example.com/test-page');

        // It should return true for a normal request
        $this->assertTrue($this->cacher->hasCachedPage($request));
    }

    #[Test]
    public function it_returns_false_for_requests_with_cache_buster_header()
    {
        // Set up a request with the buster header
        $request = Request::create('http://example.com/test-page');
        $request->headers->set('X-Statamic-Cache-Buster', 'true');

        // It should return false for a request with the cache buster header
        $this->assertFalse($this->cacher->hasCachedPage($request));
    }
}
