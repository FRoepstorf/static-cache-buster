# Static Cache Buster for Statamic

This addon enhances Statamic's static caching by allowing you to safely rebuild and swap the static cache without serving stale content during the build process.

## How It Works

The addon extends Statamic's FileCacher to recognize a special header (`X-Statamic-Cache-Buster`) that signals when a request is coming from the cache warming process. When this header is detected, the cacher will ignore any existing cached content and generate fresh content instead.

This ensures that visitors always get either the old cache or the new cache, never a mix of both, and prevents serving stale content during cache rebuilding.

It is also very handy, when you use multiple nested components to render pages: It can be difficult to track which pages are using the components, and which ones are not.

## Installation

```bash
composer require f_roepstorf/static-cache-buster
```

The addon will automatically register its service provider and extend Statamic's static cache manager to use the enhanced cacher.

## Usage

Run the cache buster command:

```bash
php artisan cache-buster:warm
```

### Options

The command supports various options:

- `--queue`: Queue the requests instead of processing them synchronously
- `--user=`: HTTP authentication user
- `--password=`: HTTP authentication password
- `--insecure`: Skip SSL verification
- `--uncached`: Only warm URLs that aren't currently cached
- `--max-depth=`: Maximum depth of URLs to warm
- `--include=`: Only warm specific URLs
- `--exclude=`: Exclude specific URLs
- `--max-requests=`: Maximum number of requests to warm
- `--temp-dir=`: Specify a custom temporary directory for the cache files

## Requirements

- Statamic 5.x
- PHP 7.4 or higher
- Static caching must be enabled and configured to use the file driver

## License

This addon is open-source software licensed under the MIT license.
