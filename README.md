# Static Cache Buster for Statamic

This addon enhances Statamic's static caching by allowing you to safely rebuild and swap the static cache without serving stale content during the build process.

## How It Works

The addon extends Statamic's FileCacher to recognize a special header (`X-Statamic-Cache-Buster`) that signals when a request is coming from the cache warming process. When this header is detected, the cacher will ignore any existing cached content and generate fresh content instead.

This ensures that visitors always get a cached response when rebuilding the cache

It is also very handy, when you use multiple nested components to render pages: It can be difficult to track which pages are using the components, and which ones are not.

## Requirements

- Statamic 5.x
- PHP 8.1 or higher(might work with lower versions)
- Static caching must be enabled and configured to use the file driver

## Installation

```bash
composer require f_roepstorf/static-cache-buster
```

For production environments, it's recommended to install with the `--prefer-dist` flag:

```bash
composer require f_roepstorf/static-cache-buster --prefer-dist
```

This ensures only the necessary files are installed, excluding development files like tests and configuration.

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
## Nginx Configuration

If you're using Nginx with the default Statamic static caching setup, you'll need to add the following to your server configuration to properly handle the cache buster header:

```nginx
set $try_location @static;

if ($request_method != GET) {
    set $try_location @not_static;
}

if ($args ~* "live-preview=(.*)") {
    set $try_location @not_static;
}

# Skip static cache when cache buster header is present
if ($http_x_statamic_cache_buster = "true") {
    set $try_location @not_static;
}

location / {
    try_files $uri $try_location;
}

location @static {
    try_files /static/${host}${uri}_$args.html $uri $uri/ /index.php?$args;
}

location @not_static {
    try_files $uri /index.php?$args;
}
```

This configuration ensures that requests with the `X-Statamic-Cache-Buster` header bypass the static cache and pass through to PHP.

## License

This addon is open-source software licensed under the MIT license.
