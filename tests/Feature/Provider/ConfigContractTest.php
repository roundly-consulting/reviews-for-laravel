<?php

declare(strict_types=1);

/**
 * The config contract, pinned in BOTH directions.
 *
 * - A key the code READS but the package never SHIPS is unreachable: the host cannot set it via
 *   the published config file, so the feature is "configurable" in theory only.
 * - A key the package SHIPS but nothing READS is a documented feature that silently does nothing.
 *
 * Keys are scraped from real PHP string TOKENS, never the raw file text — a docblock that mentions
 * a config key is not a read, and a text-based scrape passes vacuously because of it.
 */

/** @return list<string> Every `reviews.*` key the source reads (config() and ModelResolver::for()). */
function readConfigKeys(): array
{
    $keys = [];

    foreach (sourceFiles() as $file) {
        foreach (token_get_all((string) file_get_contents($file)) as $token) {
            if (! is_array($token) || $token[0] !== T_CONSTANT_ENCAPSED_STRING) {
                continue;
            }

            $literal = trim($token[1], "'\"");

            if (str_starts_with($literal, 'reviews.')) {
                $keys[] = substr($literal, strlen('reviews.'));
            }
        }
    }

    sort($keys);

    return array_values(array_unique($keys));
}

/** @return list<string> */
function sourceFiles(): array
{
    $files = [];

    foreach ([__DIR__.'/../../../src', __DIR__.'/../../../database/factories'] as $directory) {
        /** @var iterable<SplFileInfo> $iterator */
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));

        foreach ($iterator as $file) {
            if ($file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }
    }

    return $files;
}

/**
 * The shipped config file, flattened to dotted leaf keys. A list (a value the host replaces
 * wholesale, e.g. `photos.responsive_widths`) is a leaf, not a branch.
 *
 * @param  array<array-key, mixed>  $config
 * @return list<string>
 */
function shippedConfigKeys(array $config, string $prefix = ''): array
{
    $keys = [];

    foreach ($config as $key => $value) {
        $dotted = $prefix === '' ? (string) $key : $prefix.'.'.$key;

        if (is_array($value) && $value !== [] && array_keys($value) !== range(0, count($value) - 1)) {
            $keys = [...$keys, ...shippedConfigKeys($value, $dotted)];

            continue;
        }

        $keys[] = $dotted;
    }

    sort($keys);

    return array_values($keys);
}

it('ships every config key it reads', function (): void {
    /** @var array<array-key, mixed> $config */
    $config = require __DIR__.'/../../../config/reviews.php';

    // Keys the code reads that config/reviews.php never ships: unreachable from any host.
    $unreachable = array_values(array_diff(readConfigKeys(), shippedConfigKeys($config)));

    expect($unreachable)->toBe([]);
});

it('reads every config key it ships', function (): void {
    /** @var array<array-key, mixed> $config */
    $config = require __DIR__.'/../../../config/reviews.php';

    // Keys the package ships that nothing reads: a documented feature that silently does nothing.
    $dead = array_values(array_diff(shippedConfigKeys($config), readConfigKeys()));

    expect($dead)->toBe([]);
});

it('resolves the swappable models only through the Support seam', function (): void {
    $offenders = [];

    foreach (sourceFiles() as $file) {
        if (str_contains($file, '/src/Support/')) {
            continue;
        }

        foreach (token_get_all((string) file_get_contents($file)) as $token) {
            if (! is_array($token) || $token[0] !== T_CONSTANT_ENCAPSED_STRING) {
                continue;
            }

            if (in_array(trim($token[1], "'\""), ['reviews.model', 'reviews.vote_model'], true)) {
                $offenders[] = basename($file);
            }
        }
    }

    // A model key read outside Support/ is a half-honoured seam: the host swaps the model and the
    // package obeys it in some call sites and not others (the certificates/media class of bug).
    expect(array_values(array_unique($offenders)))->toBe([]);
});
