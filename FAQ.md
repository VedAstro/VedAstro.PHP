# FAQ

Short answers to the questions that come up most. The [README](README.md) has runnable examples
for each of them.

**Which PHP versions are supported?** PHP 8.1 or newer. The library uses native backed enums,
readonly properties and constructor property promotion, all of which are standard from 8.1.

**Does it have any dependencies?** No runtime dependencies. `json` is used for encoding and
decoding, cURL when the extension is loaded, and PHP's HTTP stream wrapper otherwise. PHPUnit is
not needed either: the test suite is a plain PHP script.

**Which extensions do I need?** Only `json`, which is bundled and enabled by default. cURL is
preferred when present but optional. HTTPS through the stream transport needs `openssl`, which is
present in essentially every PHP build; on the rare build without it, enable cURL instead.

**How do I install it?** `composer require vedastro/vedastro`. Without Composer, include
`src/autoload.php` (or `src/VedAstro.php`) directly — the repository ships both.

**Is this a calculation engine?** No. It is a client for the remote VedAstro API, so every
calculation needs network access. The astrology itself runs on VedAstro's servers.

**Are calls blocking?** Yes. Every calculator blocks the calling process until the API responds.
That suits PHP-FPM workers, CLI scripts and queue consumers.

**Can I use it in a long-running worker?** Yes. A `VedAstroClient` is immutable after construction
and stateless between calls, so one instance serves any number of calls.

**How do I set the API key?** `new VedAstroClient(['apiKey' => getenv('VEDASTRO_API_KEY')])`, or
`Calculate::setApiKey(...)` for the static facade. An empty key uses the free tier. Never commit a
paid key.

**What are the rate limits?** The free tier allows five requests per minute, counted per key
across every process of your application. Paid keys are quoted as 200 calls per minute by the
API's own rate-limit error. Confirm current limits at
[vedastro.org/API.html](https://vedastro.org/API.html).

**Does it retry or throttle automatically?** Neither. Only the caller knows whether a second
request is safe, so the library leaves both to you. `examples/batch_processing.php` shows a paced,
cached version.

**Why do methods return `mixed`?** Results vary per calculator: objects, arrays, scalars, or SVG
markup. Use the `Response` helpers (`Response::string`, `Response::float`, `Response::int`,
`Response::bool`, `Response::list`, `Response::get`, `Response::has`) which return a fallback
instead of throwing when the shape is not what you expected.

**Why did my payload come back as a plain value instead of an array?** A payload object with
exactly one property is unwrapped to that property's value, the same rule the Python, Node.js, Go
and Java SDKs use. Since the API wraps every result as `{"<MethodName>": ...}`, most calculators
return the inner value directly — so `planetRasiD1Sign` may return an array while `houseSignName`
returns a bare string.

**Is a number an `int` or a `float`?** Whatever `json_decode` produced: `7` decodes to `int`,
`65.0` to `float`. Use `Response::float()` or `Response::int()` when you need a specific type.

**A call failed — what should I check?** In order: the API key, the time string
(`"HH:MM DD/MM/YYYY +HH:MM"`, 24-hour, DD/MM/YYYY), the coordinate order
(`GeoLocation::of($name, $longitude, $latitude)`), and the rate limit.
`ApiException::getMessage()` carries the API's own explanation.

**How do I stop a request that is taking too long?** Set a short `timeout` option on the client so
it fails fast, rather than relying on the web server's own timeout.

**Can two requests use different ayanamsas at once?** PHP handles one request per process, so
`withAyanamsa()` is per call: it applies the override, runs your closure, then restores the
previous value even if the closure throws. A client-wide default is set with the
`defaultAyanamsa` option.

**Which ayanamsa should I use?** `Ayanamsa::LAHIRI` for Indian astrology (and it is the server
default when you set nothing), `Ayanamsa::KRISHNAMURTI` for KP, `Ayanamsa::FAGAN_BRADLEY` for
Western sidereal.

**Why does creating a client print a line of text?** It prints the familiar one-line banner once
per process, matching the other SDKs. Pass `'silent' => true` to suppress it — do that in any
handler that writes a response body, where stray output would corrupt the response.

**How do I run the tests?** `php tests/run.php`. They need no network, no API key and no Composer:
most cases drive the client through a fake transport, and the integration case registers a stream
wrapper so the real transport performs a genuine request/response cycle in-process. Use
`php tests/live_check.php` when you want to verify against the live API, which does need network
access and spends free-tier calls.

**Can I call an endpoint that the library does not wrap yet?** Yes:
`$client->call('NewEndpointName', ['time' => $birth])`. Useful while the server is ahead of a
regenerated client.

**Can I use this commercially?** Yes. MIT licensed, and both the free and paid tiers permit
commercial use.
