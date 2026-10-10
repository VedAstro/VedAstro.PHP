# Quick start

Requires **PHP 8.1 or newer** with the `json` extension (bundled by default). There is nothing
else to install — no native code, no ephemeris files, no third-party runtime dependencies.

## 1. Install

```bash
composer require vedastro/vedastro
```

## 2. Make a calculation

Save this as `sun-sign.php`:

```php
<?php

require 'vendor/autoload.php';

use VedAstro\Calculate;
use VedAstro\Generated\PlanetName;
use VedAstro\GeoLocation;
use VedAstro\Response;
use VedAstro\Time;

// 'FreeAPIUser' is the free-tier key (5 requests per minute).
// For a paid key, set VEDASTRO_API_KEY and read it with getenv().
Calculate::setApiKey('FreeAPIUser');

// Time format: "HH:MM DD/MM/YYYY +TZ:TZ".
// GeoLocation takes the name, then LONGITUDE, then latitude.
$birth = Time::of('14:30 25/10/1992 +05:30', GeoLocation::of('Mumbai', 72.8777, 19.0760));

$result = Calculate::planetRasiD1Sign(PlanetName::SUN, $birth);

echo 'Sun Sign: ', Response::string($result, 'Name'), PHP_EOL; // Libra
```

## 3. Run it

```bash
php sun-sign.php
```

Expected output:

```
Sun Sign: Libra
```

## Running the bundled demos

Twelve demos ship in `examples/`. They read `VEDASTRO_API_KEY` and fall back to the free tier:

```bash
php examples/quick_start.php
php examples/daily_panchanga.php
php examples/marriage_compatibility.php
```

See the [demo list](README.md#-common-use-cases--demo-files) for the rest.

## Where to go next

- **Results are dynamic** — calculators return `mixed`. Read fields with `Response::string()`,
  `Response::float()`, `Response::int()`, `Response::bool()`, `Response::list()` and
  `Response::get()` rather than casting blindly. They never throw on an unexpected shape.
- **Optional arguments** — thirty calculators have an options class in `VedAstro\Generated`.
  A null property is omitted, so the server applies its own default.
- **Servers** — build one `VedAstroClient` per process and inject it; silence the banner in
  anything that writes a response body. Keep the client `timeout` below PHP's
  `max_execution_time` (30s under a default PHP-FPM pool), because PHP's limit kills the request
  with an uncatchable fatal error while the SDK's timeout raises a catchable exception. See
  [Using it in a server](README.md#-using-it-in-a-server).
- **Rate limits** — the free tier allows five requests per minute across all processes. Use
  `usleep()` for an exact 13-second gap; `sleep()` takes whole seconds.
- **Errors** — catch `VedAstro\ApiException` for API failures and `VedAstro\VedAstroException`
  for transport failures. The API key is always redacted.
- **Common problems** — [FAQ](FAQ.md)

## Without Composer

The repository ships its own autoloader, so the library also works when vendored directly:

```php
require __DIR__ . '/path/to/vedastro/src/autoload.php';
```
