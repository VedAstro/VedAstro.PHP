<?php

declare(strict_types=1);

namespace VedAstro\Tests;

use VedAstro\Transport;
use VedAstro\TransportResponse;
use VedAstro\VedAstroException;

/**
 * A transport that records the requests it receives and replays a canned transport.
 *
 * Using it keeps the test suite fast, deterministic, and free of network access, while still
 * exercising the real client: request building, JSON encoding, and response decoding all run.
 */
final class FakeTransport implements Transport
{
    /** @var list<array{url: string, body: string}> */
    private array $requests = [];

    private TransportResponse $response;

    private ?VedAstroException $failure = null;

    public function __construct(?TransportResponse $response = null)
    {
        $this->response = $response ?? new TransportResponse(
            200,
            '{"Status":"Pass","Payload":{}}',
            'application/json'
        );
    }

    public function post(string $url, string $body): TransportResponse
    {
        if ($this->failure !== null) {
            throw $this->failure;
        }

        $this->requests[] = ['url' => $url, 'body' => $body];

        return $this->response;
    }

    /** Queues a response for the next request. */
    public function willRespond(TransportResponse $response): void
    {
        $this->response = $response;
    }

    /** Makes the next request fail at the transport level. */
    public function willFail(VedAstroException $failure): void
    {
        $this->failure = $failure;
    }

    /** The number of requests received. */
    public function requestCount(): int
    {
        return count($this->requests);
    }

    /** The URL of the most recent request. */
    public function lastUrl(): string
    {
        return $this->requests[array_key_last($this->requests)]['url'];
    }

    /**
     * The decoded body of the most recent request.
     *
     * @return array<string, mixed>
     */
    public function lastBody(): array
    {
        $decoded = json_decode($this->requests[array_key_last($this->requests)]['body'], true);

        return is_array($decoded) ? $decoded : [];
    }
}
