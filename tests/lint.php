<?php

declare(strict_types=1);

/**
 * Lints every PHP file in the repository.
 *
 * Run it with `php tests/lint.php`, or `composer lint`. Uses `php -l` on each file, which needs
 * no dependencies and catches syntax errors that would otherwise surface at runtime.
 */

$root = dirname(__DIR__);
$directories = [$root . '/src', $root . '/tests', $root . '/examples'];

$checked = 0;
$failures = [];

foreach ($directories as $directory) {
    if (!is_dir($directory)) {
        continue;
    }

    $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(
        $directory,
        FilesystemIterator::SKIP_DOTS
    ));

    foreach ($iterator as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $checked++;
        $output = [];
        $exitCode = 0;
        exec(sprintf('%s -l %s 2>&1', escapeshellarg(PHP_BINARY), escapeshellarg($file->getPathname())), $output, $exitCode);

        if ($exitCode !== 0) {
            $failures[] = $file->getPathname() . ': ' . implode(' ', $output);
        }
    }
}

printf('linted %d file(s)%s', $checked, PHP_EOL);

if ($failures !== []) {
    foreach ($failures as $failure) {
        echo '  ✗ ', $failure, PHP_EOL;
    }
    exit(1);
}

echo 'All files pass php -l.', PHP_EOL;
exit(0);
