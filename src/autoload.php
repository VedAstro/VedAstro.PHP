<?php

declare(strict_types=1);

/**
 * A dependency-free PSR-4 autoloader for the VedAstro SDK.
 *
 * Composer generates an equivalent autoloader from `composer.json`; this file exists so the
 * library, its tests, and its demos run with no tooling installed beyond PHP itself.
 *
 * ```php
 * require __DIR__ . '/src/autoload.php';
 * ```
 */
spl_autoload_register(static function (string $class): void {
    $prefix = 'VedAstro\\';
    if (!str_starts_with($class, $prefix)) {
        return;
    }

    // The generated optional-argument holders share a single file, which a PSR-4 lookup by
    // class name cannot find. The generator also ships src/VedAstro.php, which requires it
    // explicitly; this covers Composer-style autoloading of an individual options class.
    if (str_starts_with($class, 'VedAstro\\Generated\\')) {
        $options = __DIR__ . '/Generated/Options.php';
        if (is_file($options)) {
            require_once $options;
        }
    }

    $relative = substr($class, strlen($prefix));
    $path = __DIR__ . '/' . str_replace('\\', '/', $relative) . '.php';

    if (is_file($path)) {
        require_once $path;
    }
});
