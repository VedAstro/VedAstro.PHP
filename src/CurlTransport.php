<?php

declare(strict_types=1);

namespace VedAstro;

/**
 * A transport built on the cURL extension.
 *
 * cURL is fast and handles proxies and redirects well, but it needs a certificate authority
 * bundle. PHP's stream transport finds one through `openssl` automatically, while cURL needs
 * `curl.cainfo` (or `openssl.cafile`) set in php.ini — a common source of the error
 * "SSL certificate problem: unable to get local issuer certificate".
 *
 * This class therefore looks for a bundle itself when php.ini has not set one, and the SDK
 * chooses {@see StreamTransport} by default for that reason. Ask for cURL explicitly with
 * `['transport' => new CurlTransport()]` once it is configured correctly.
 */
final class CurlTransport implements Transport
{
    /** Places a CA bundle commonly lives, on Linux and macOS builds. */
    private const CA_BUNDLE_CANDIDATES = [
        '/etc/ssl/certs/ca-certificates.crt',
        '/etc/pki/tls/certs/ca-bundle.crt',
        '/etc/ssl/cert.pem',
        '/usr/local/etc/openssl/cert.pem',
        '/opt/homebrew/etc/openssl@3/cert.pem',
    ];

    private readonly ?string $caBundle;
    private readonly ?int $timeoutSeconds;

    /**
     * @param int|null $timeoutSeconds Seconds to wait for the whole request, including the body.
     *                                 Null, the default, applies no deadline: a VedAstro
     *                                 calculation can legitimately take minutes, and a library
     *                                 cannot know what is safe for the caller. Set one only when
     *                                 your own code has decided a request has run too long.
     * @param string|null $caBundle An explicit CA bundle path, or null to detect one.
     */
    public function __construct(
        ?int $timeoutSeconds = null,
        ?string $caBundle = null,
    ) {
        $this->timeoutSeconds = $timeoutSeconds;
        $this->caBundle = $caBundle ?? self::detectCaBundle();
    }

    /**
     * The CA bundle in use, or null when none was found and cURL falls back to its own default.
     *
     * When this is null, HTTPS requests will usually fail with "unable to get local issuer
     * certificate" on a PHP build without a configured `curl.cainfo`. Set one in php.ini, or pass
     * a path to the constructor, or use {@see StreamTransport} instead.
     */
    public function caBundle(): ?string
    {
        return $this->caBundle;
    }

    public function post(string $url, string $body): TransportResponse
    {        $handle = curl_init($url);
        if ($handle === false) {
            throw new VedAstroException('vedastro: cannot initialise cURL');
        }

        $options = [
            CURLOPT_POST => true,
            CURLOPT_POSTFIELDS => $body,
            CURLOPT_HTTPHEADER => [
                'Content-Type: application/json',
                'Accept: application/json, image/svg+xml',
            ],
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_MAXREDIRS => 5,
        ];

        if ($this->timeoutSeconds !== null) {
            $options[CURLOPT_TIMEOUT] = $this->timeoutSeconds;
            $options[CURLOPT_CONNECTTIMEOUT] = $this->timeoutSeconds;
        } else {
            // 0 means "no timeout" to libcurl. Set explicitly so a value compiled into libcurl, or
            // a CURLOPT_* default set elsewhere in the process, cannot impose one silently.
            $options[CURLOPT_TIMEOUT] = 0;
            $options[CURLOPT_CONNECTTIMEOUT] = 0;
        }

        if ($this->caBundle !== null) {
            // Verification stays on; cURL is just told where the roots are.
            $options[CURLOPT_CAINFO] = $this->caBundle;
        }

        curl_setopt_array($handle, $options);

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

    /**
     * Finds a usable CA bundle: the explicit ini setting first, then well-known locations and
     * any `cacert.pem` shipped beside php.ini.
     *
     * Returns null when nothing is found, in which case cURL falls back to its own compiled-in
     * default.
     */
    private static function detectCaBundle(): ?string
    {
        foreach (['curl.cainfo', 'openssl.cafile'] as $setting) {
            $configured = ini_get($setting);
            if (is_string($configured) && $configured !== '' && is_file($configured)) {
                return $configured;
            }
        }

        // Windows builds ship cacert.pem beside php.ini; derive the path rather than hard-code it.
        $iniFile = php_ini_loaded_file();
        if (is_string($iniFile) && $iniFile !== '') {
            $iniDirectory = dirname($iniFile);
            foreach (['/extras/ssl/cacert.pem', '/cacert.pem'] as $relative) {
                $candidate = $iniDirectory . $relative;
                if (is_file($candidate)) {
                    return $candidate;
                }
            }
        }

        foreach (self::CA_BUNDLE_CANDIDATES as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
