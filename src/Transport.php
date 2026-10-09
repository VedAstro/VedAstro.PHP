<?php

declare(strict_types=1);

namespace VedAstro;

/**
 * Sends one HTTP request.
 *
 * Implement this to route the SDK through a proxy, a stub, or a custom stack; otherwise the SDK
 * picks {@see StreamTransport} (or {@see CurlTransport} when the cURL extension is loaded).
 *
 * Implementations are stateless and therefore safe to share between calls.
 */
interface Transport
{
    /**
     * Performs a blocking POST.
     *
     * @param string $url  The absolute endpoint URL.
     * @param string $body The JSON request body.
     *
     * @throws VedAstroException When the request cannot be completed.
     */
    public function post(string $url, string $body): TransportResponse;
}
