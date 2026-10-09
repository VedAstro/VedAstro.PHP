<?php

declare(strict_types=1);

namespace VedAstro\Tests;

/**
 * An in-process HTTP fixture for the integration test.
 *
 * A stream wrapper is registered for a private scheme, so {@see \VedAstro\StreamTransport} can
 * perform a genuine HTTP round trip — real request body, real headers, real response parsing —
 * without a socket, a subprocess, or network access. That keeps the test fast and, more
 * importantly, leaves nothing behind to keep the test runner alive.
 *
 * The canned responses mirror what the live service returns for these endpoints.
 */
final class StreamWrapperFixture
{
    public const SCHEME = 'vedastro-test';

    /** The headers the last request carried. */
    public static array $lastHeaders = [];

    /** The decoded JSON body of the last request. */
    public static array $lastBody = [];

    /** The number of requests served. */
    public static int $requestCount = 0;

    /** The status line the next response will carry. */
    public static int $statusCode = 200;

    /** The Content-Type the next response will carry. */
    public static string $contentType = 'application/json';

    /** The body the next response will carry. */
    public static string $body = '{"Status":"Pass","Payload":{}}';

    public static function register(): void
    {
        if (in_array(self::SCHEME, stream_get_wrappers(), true)) {
            stream_wrapper_unregister(self::SCHEME);
        }

        stream_wrapper_register(self::SCHEME, self::class);
    }

    public static function unregister(): void
    {
        if (in_array(self::SCHEME, stream_get_wrappers(), true)) {
            stream_wrapper_unregister(self::SCHEME);
        }
    }

    /** Builds the envelope the API returns: `{"Status":"Pass","Payload":{"<Name>": <payload>}}`. */
    public static function envelope(string $name, mixed $payload): string
    {
        return (string) json_encode([
            'Status' => 'Pass',
            'Input' => ['CalculatorName' => $name, 'Type' => 'SingleCall', 'Parameters' => self::$lastBody],
            'Payload' => [$name => $payload],
        ]);
    }

    // ---------------------------------------------------------------- stream wrapper

    /** @var resource|null */
    public $context;

    private int $position = 0;

    public function stream_open(string $path, string $mode, int $options, ?string &$openedPath): bool
    {
        self::$requestCount++;

        // The wrapper sees the raw request, so records what the client actually sent.
        $context = stream_context_get_options($this->context ?? stream_context_get_default());
        $headers = $context['http']['header'] ?? '';
        self::$lastHeaders = is_string($headers)
            ? array_values(array_filter(explode("\r\n", $headers)))
            : [];
        $content = $context['http']['content'] ?? '';
        $decoded = is_string($content) ? json_decode($content, true) : null;
        self::$lastBody = is_array($decoded) ? $decoded : [];

        // A stream wrapper is included into the calling scope, but the transport clears its own
        // $http_response_header before the request, so a custom wrapper must publish through
        // $GLOBALS. The transport reads that as a fallback, which is also how a real custom
        // wrapper (a proxy, say) can report a status line.
        $reason = self::$statusCode === 200 ? 'OK' : 'Error';
        $GLOBALS['http_response_header'] = [
            sprintf('HTTP/1.1 %d %s', self::$statusCode, $reason),
            'Content-Type: ' . self::$contentType,
        ];

        $this->position = 0;

        return true;
    }

    public function stream_read(int $count): string
    {
        $chunk = substr(self::$body, $this->position, $count);
        $this->position += strlen($chunk);

        return $chunk;
    }

    public function stream_eof(): bool
    {
        return $this->position >= strlen(self::$body);
    }

    public function stream_stat(): array
    {
        return ['size' => strlen(self::$body), 'mode' => 0100644];
    }

    /** @return array<int|string, mixed> */
    public function url_stat(string $path, int $flags): array
    {
        return $this->stream_stat();
    }

    /** Required by the wrapper protocol; the fixture never seeks. */
    public function stream_seek(int $offset, int $whence = SEEK_SET): bool
    {
        return false;
    }

    public function stream_tell(): int
    {
        return $this->position;
    }
}
