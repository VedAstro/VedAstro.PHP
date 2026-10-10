<?php

declare(strict_types=1);

namespace VedAstro;

/**
 * The default transport, built on PHP's HTTP stream wrapper.
 *
 * It needs no extension beyond `allow_url_fopen` (on by default) and uses `openssl` for HTTPS,
 * which is present in virtually every PHP build. Errors are suppressed so the response body of a
 * non-2xx reply can still be read, and the status line is recovered from
 * `$http_response_header` — the only way the stream wrapper exposes it.
 *
 * No request deadline is applied unless you supply one: see {@see __construct()}.
 */
final class StreamTransport implements Transport
{
    private readonly ?int $timeoutSeconds;

    /**
     * @param int|null $timeoutSeconds Seconds to wait for the whole request, including the body.
     *                                 Null, the default, applies no deadline: a VedAstro
     *                                 calculation can legitimately take minutes, and a library
     *                                 cannot know what is safe for the caller.
     *
     *                                 Note that PHP still applies its own
     *                                 `default_socket_timeout` (60 seconds as shipped) to each
     *                                 socket read. That is an environment setting, not something
     *                                 this library imposes; raise it if you need longer
     *                                 individual requests.
     */
    public function __construct(?int $timeoutSeconds = null)
    {
        $this->timeoutSeconds = $timeoutSeconds;
    }

    public function post(string $url, string $body): TransportResponse
    {
        $http = [
            'method' => 'POST',
            'header' => implode("\r\n", [
                'Content-Type: application/json',
                'Accept: application/json, image/svg+xml',
                'Content-Length: ' . strlen($body),
            ]),
            'content' => $body,
            // Read the body even on 4xx/5xx instead of failing outright.
            'ignore_errors' => true,
            // Follow redirects, matching the other SDKs.
            'follow_location' => 1,
            'max_redirects' => 5,
        ];

        if ($this->timeoutSeconds !== null) {
            $http['timeout'] = $this->timeoutSeconds;
        }

        $context = stream_context_create(['http' => $http]);

        // Clear any stale headers from an earlier request, then perform the request. PHP fills
        // $http_response_header for the built-in HTTP wrapper; a custom wrapper cannot reach this
        // scope, so it may publish the same information through $GLOBALS instead.
        $http_response_header = [];
        $GLOBALS['http_response_header'] = [];
        $response = @file_get_contents($url, false, $context);

        if ($response === false) {
            $error = error_get_last();
            throw new VedAstroException(sprintf(
                'vedastro: HTTP request to %s failed (%s)',
                $url,
                $error['message'] ?? 'unknown transport error'
            ));
        }

        $statusCode = 0;
        $contentType = '';

        $responseHeaders = $http_response_header;
        if ($responseHeaders === [] && is_array($GLOBALS['http_response_header'] ?? null)) {
            // A custom wrapper (a proxy, or a test fixture) reported its own headers.
            $responseHeaders = array_values(array_filter($GLOBALS['http_response_header'], 'is_string'));
        }
        foreach ($responseHeaders as $header) {
            if (preg_match('#^HTTP/\S+\s+(\d{3})#', $header, $matches) === 1) {
                // Keep the last status line so redirects report their final status.
                $statusCode = (int) $matches[1];
            } elseif (stripos($header, 'content-type:') === 0) {
                $contentType = trim(substr($header, strlen('content-type:')));
            }
        }

        return new TransportResponse($statusCode, $response, $contentType);
    }
}
