<?php

declare(strict_types=1);

namespace FRoepstorf\StaticCacheBuster\Commands;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Pool;
use GuzzleHttp\Psr7\Message;
use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Response;
use Illuminate\Console\Command;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Routing\Route;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Statamic\Console\Commands\StaticWarmJob;
use Statamic\Console\Commands\StaticWarmUncachedJob;
use Statamic\Console\EnhancesCommands;
use Statamic\Console\RunsInPlease;
use Statamic\Entries\Collection as EntriesCollection;
use Statamic\Entries\Entry;
use Statamic\Facades;
use Statamic\Facades\Site;
use Statamic\Facades\Term;
use Statamic\Facades\URL;
use Statamic\Http\Controllers\FrontendController;
use Statamic\StaticCaching\Cacher as StaticCacher;
use Statamic\Support\Traits\Hookable;
use Statamic\Taxonomies\LocalizedTerm;
use Statamic\Taxonomies\Taxonomy;

class StaticCacheBusterCommand extends Command
{
    use EnhancesCommands;
    use Hookable;
    use RunsInPlease;

    protected $signature = 'cache-buster:warm
        {--queue : Queue the requests}
        {--u|user= : HTTP authentication user}
        {--p|password= : HTTP authentication password}
        {--insecure : Skip SSL verification}
        {--uncached : Only warm uncached URLs}
        {--max-depth= : Maximum depth of URLs to warm}
        {--include= : Only warm specific URLs}
        {--exclude= : Exclude specific URLs}
        {--max-requests= : Maximum number of requests to warm}
        {--temp-dir= : Temporary directory for cache files}
    ';

    protected $description = 'Warms the static cache by visiting all URLs and safely swapping the cache directory';

    protected $shouldQueue = false;

    protected $queueConnection;

    private $uris;

    public function handle()
    {
        if (! config('statamic.static_caching.strategy')) {
            $this->components->error('Static caching is not enabled.');

            return 1;
        }

        // Only works with file driver
        $strategy = config('statamic.static_caching.strategy');
        $driver = config("statamic.static_caching.strategies.$strategy.driver");

        if ($driver !== 'file') {
            $this->components->error('This command only works with the file driver for static caching.');

            return 1;
        }

        $this->shouldQueue = $this->option('queue');
        $this->queueConnection = config('statamic.static_caching.warm_queue_connection') ?? config('queue.default');

        if ($this->shouldQueue && $this->queueConnection === 'sync') {
            $this->components->error('The queue connection is set to "sync". Queueing will be disabled.');
            $this->shouldQueue = false;
        }

        $this->comment('Please wait. This may take a while if you have a lot of content.');

        // Perform the warming
        $this->warm();

        $this->components->info(
            $this->shouldQueue
                ? 'All requests to warm the static cache have been added to the queue.'
                : 'The static cache has been warmed and swapped into place.'
        );

        return 0;
    }

    private function warm(): void
    {
        $client = new Client($this->clientConfig());

        $this->output->newLine();
        $this->line('Compiling URLs...');

        $requests = $this->requests();

        $this->output->newLine();

        if ($this->shouldQueue) {
            $queue = config('statamic.static_caching.warm_queue');
            $this->line(sprintf('Adding %s requests onto %squeue...', count($requests), $queue ? $queue.' ' : ''));

            $jobClass = $this->option('uncached')
                ? StaticWarmUncachedJob::class
                : StaticWarmJob::class;

            foreach ($requests as $request) {
                $jobClass::dispatch($request, $this->clientConfig())
                    ->onConnection($this->queueConnection)
                    ->onQueue($queue);
            }
        } else {
            $this->line('Visiting '.count($requests).' URLs...');

            $pool = new Pool($client, $requests, [
                'concurrency' => $this->concurrency(),
                'fulfilled'   => $this->outputSuccessLine(...),
                'rejected'    => $this->outputFailureLine(...),
            ]);

            $promise = $pool->promise();

            $promise->wait();
        }
    }

    private function concurrency(): int
    {
        $strategy = config('statamic.static_caching.strategy');

        return config("statamic.static_caching.strategies.$strategy.warm_concurrency", 25);
    }

    private function clientConfig(): array
    {
        return [
            'verify' => $this->shouldVerifySsl(),
            'auth'   => $this->option('user') && $this->option('password')
                ? [$this->option('user'), $this->option('password')]
                : null,
            'headers' => [
                'X-Statamic-Cache-Buster' => 'true',
            ],
        ];
    }

    public function outputSuccessLine(Response $response, $index): void
    {
        $this->components->twoColumnDetail($this->getRelativeUri($index), '<info>✓ Cached</info>');
    }

    public function outputFailureLine($exception, $index): void
    {
        $uri = $this->getRelativeUri($index);

        if ($exception instanceof RequestException && $exception->hasResponse()) {
            $response = $exception->getResponse();

            $message = $response->getStatusCode().' '.$response->getReasonPhrase();

            if ($response->getStatusCode() == 500) {
                $message .= "\n".Message::bodySummary($response, 500);
            }
        } else {
            $message = $exception->getMessage();
        }

        $this->components->twoColumnDetail($uri, "<fg=cyan>$message</fg=cyan>");
    }

    private function getRelativeUri(int $index): string
    {
        return Str::start(Str::after($this->uris()->get($index), config('app.url')), '/');
    }

    private function requests()
    {
        return $this->uris()->map(fn ($uri) => new Request('GET', $uri, [
            'X-Statamic-Cache-Buster' => 'true',
        ]))->all();
    }

    private function uris(): Collection
    {
        if ($this->uris) {
            return $this->uris;
        }

        $cacher = app(StaticCacher::class);

        return $this->uris = collect()
            ->merge($this->entryUris())
            ->merge($this->taxonomyUris())
            ->merge($this->termUris())
            ->merge($this->customRouteUris())
            ->merge($this->additionalUris())
            ->unique()
            ->filter(fn ($uri) => $this->shouldInclude($uri))
            ->reject(fn ($uri) => $this->shouldExclude($uri))
            ->reject(fn ($uri) => $this->exceedsMaxDepth($uri))
            ->reject(function ($uri) use ($cacher) {
                if ($this->option('uncached') && $cacher->hasCachedPage(HttpRequest::create($uri))) {
                    return true;
                }

                Site::resolveCurrentUrlUsing(fn () => $uri);

                // Just return false since we want to generate all URLs in our new directory anyway
                return false;
            })
            ->sort()
            ->values()
            ->when($this->option('max-requests'), fn ($uris, $max) => $uris->take($max));
    }

    private function shouldInclude($uri): bool
    {
        if (! $inclusions = $this->option('include')) {
            return true;
        }

        $inclusions = explode(',', $inclusions);

        return collect($inclusions)->contains(fn ($included) => $this->uriMatches($uri, $included));
    }

    private function shouldExclude($uri): bool
    {
        if (! $exclusions = $this->option('exclude')) {
            return false;
        }

        $exclusions = explode(',', $exclusions);

        return collect($exclusions)->contains(fn ($excluded) => $this->uriMatches($uri, $excluded));
    }

    private function uriMatches($uri, $pattern): bool
    {
        $uri = URL::makeRelative($uri);

        if (Str::endsWith($pattern, '*')) {
            $prefix = Str::removeRight($pattern, '*');

            if (Str::startsWith($uri, $prefix) && ! (Str::endsWith($prefix, '/') && $uri === $prefix)) {
                return true;
            }
        } elseif (Str::removeRight($uri, '/') === Str::removeRight($pattern, '/')) {
            return true;
        }

        return false;
    }

    private function exceedsMaxDepth($uri): bool
    {
        if (! $max = $this->option('max-depth')) {
            return false;
        }

        return count(explode('/', trim(URL::makeRelative($uri), '/'))) > $max;
    }

    private function shouldVerifySsl(): bool
    {
        if ($this->option('insecure')) {
            return false;
        }

        // Get the app's environment
        $environment = app()->environment();

        return ! in_array($environment, ['local', 'testing']);
    }

    protected function entryUris(): Collection
    {
        $this->line('[ ] Entries...');

        // "Warm" the structure trees
        Facades\Collection::whereStructured()->each(fn ($collection) => $collection->structure()->trees()->each->tree());

        $entries = Facades\Entry::all()->map(function (Entry $entry) {
            if (! $entry->published() || $entry->private()) {
                return null;
            }

            if ($entry->isRedirect()) {
                return null;
            }

            return $entry->absoluteUrl();
        })->filter();

        $this->line("\x1B[1A\x1B[2K<info>[✔]</info> Entries");

        return $entries;
    }

    protected function taxonomyUris(): Collection
    {
        $this->line('[ ] Taxonomies...');

        $taxonomyUris = Facades\Taxonomy::all()
            ->filter(fn ($taxonomy) => view()->exists($taxonomy->template()))
            ->flatMap(fn (Taxonomy $taxonomy) => $taxonomy->sites()->map(function ($site) use ($taxonomy) {
                // Needed because Taxonomy uses the current site. If the Taxonomy
                // class ever gets its own localization logic we can remove this.
                Site::setCurrent($site);

                return $taxonomy->absoluteUrl();
            }));

        $this->line("\x1B[1A\x1B[2K<info>[✔]</info> Taxonomies");

        return $taxonomyUris;
    }

    protected function termUris(): Collection
    {
        $this->line('[ ] Taxonomy terms...');

        $terms = Term::all()
            ->merge($this->scopedTerms())
            ->filter(fn ($term) => view()->exists($term->template()))
            ->flatMap(fn (LocalizedTerm $localizedTerm) => $localizedTerm->taxonomy()->sites()->map(fn ($site) => $localizedTerm->in($site)->absoluteUrl()));

        $this->line("\x1B[1A\x1B[2K<info>[✔]</info> Taxonomy terms");

        return $terms;
    }

    protected function scopedTerms(): Collection
    {
        return Facades\Collection::all()
            ->flatMap(fn (EntriesCollection $entriesCollection) => $this->getCollectionTerms($entriesCollection));
    }

    protected function getCollectionTerms($collection)
    {
        return $collection->taxonomies()
            ->flatMap(fn (Taxonomy $taxonomy) => $taxonomy->queryTerms()->get())
            ->map->collection($collection);
    }

    protected function customRouteUris(): Collection
    {
        $this->line('[ ] Custom routes...');

        $action = FrontendController::class.'@route';

        $routes = collect(app('router')->getRoutes()->getRoutes())
            ->filter(fn (Route $route) => $route->getActionName() === $action && ! Str::contains($route->uri(), '{'))
            ->map(fn (Route $route) => URL::tidy(Str::start($route->uri(), config('app.url').'/')));

        $this->line("\x1B[1A\x1B[2K<info>[✔]</info> Custom routes");

        return $routes;
    }

    protected function additionalUris(): Collection
    {
        $this->line('[ ] Additional...');

        $uris = $this->runHooks('additional', collect());

        $this->line("\x1B[1A\x1B[2K<info>[✔]</info> Additional");

        return $uris->map(fn ($uri) => URL::makeAbsolute($uri));
    }
}
