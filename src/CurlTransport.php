<?php

declare(strict_types=1);

namespace VedAstro;

/**
 * A transport built on the cURL extension.
 *
 * cURL is more forgiving than the stream wrapper about TLS and proxies, so the SDK prefers it
 * when the extension is loaded. Use it explicitly if a build has `allow_url_fopen` disabled.
 */
final class CurlTransport implements Transport
{
    /**
     * @param int $timeoutSeconds Seconds to wait for the whole request, including the body.
     */
    public function __construct(private readonly int $timeoutSeconds = 120)
    {
    }

    public function post(string $url, string $body): TransportResponse
    {
        $handle = curl_init($url);
        if ($handle === false) {
            throw new VedAstroException('vedastro: cannot initialise cURL');
        }

        curl_setopt_array($handle, [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json, image/svg+xml',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
            CURLOPT_TIMEOUT => $this->timeoutSeconds,
            CURLOPT_CONNECTTIMEOUT => 30,
        ]);

        $response = curl_exec($handle);
        if ($response === false) {
            $message = curl_error($handle);
            curl_close($handle);
            throw new VedAstroException(sprintf(
                'vedastro: HTTP request to %s failed (%s)',
                $url,
                $message !== '' ? $message : 'unknown transport error'
            ));
        }

        $statusCode = (int) curl_getinfo($handle, CURLINFO_RESPONSE_CODE);
        $contentType = (string) (curl_getinfo($handle, CURLINFO_CONTENT_TYPE) ?? '');
        curl_close($handle);

        return new TransportResponse($statusCode, (string) $response, $contentType);
    }
}
