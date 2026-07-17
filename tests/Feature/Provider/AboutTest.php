<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

/**
 * A — the secret-safe `about` capture.
 *
 * `php artisan about --only=reviews` reports switches, bounds and presence — never the
 * moderation blocklist itself (printing it hands anyone reading the output the exact word
 * list the filter screens for) and never the photos disk by name.
 *
 * The local `aboutOutput()` helper this file carried was already non-vacuous: it went
 * through `Artisan::call()` + `Artisan::output()`, not `app(Kernel::class)->output()` —
 * the `''` that made purchases #13's entire leak check pass against empty output. The
 * preset is adopted anyway because it makes that ordering structural rather than a habit:
 * `mustRender` is required and non-empty, and the capture is asserted non-empty and proven
 * to have rendered *before* any secret is looked for. A negative-only case cannot be
 * written with it.
 */
function aboutOutput(): string
{
    Artisan::call('about', ['--only' => 'reviews']);

    return Artisan::output();
}

it('reports the moderation blocklist and photo disk without leaking either', function (): void {
    config()->set('reviews.moderation.banned_words', ['scandalous', 'unspeakable']);
    config()->set('reviews.photos.disk', 'super-secret-bucket');

    expect('reviews')->toLeakNoSecrets(
        secrets: [
            // The blocklist is the host's moderation policy: printing a term hands a reader
            // the exact word the filter screens for. Reported by count only.
            'scandalous',
            'unspeakable',
            // A disk name is host infrastructure. Reported by presence only.
            'super-secret-bucket',
        ],
        mustRender: [
            'Review model',
            'Rating scale',
            '1-5',
            'Cached aggregates',
            'Photos',
            'Banned words',
            'Photo disk',
            // The positive halves that prove the lines report rather than sit empty: the
            // count itself, and the presence marker. Without these the secret checks above
            // would be aimed at a section that might have printed nothing at all.
            '2 term(s)',
            'SET',
        ],
    );
});

it('reports an unset photos disk as the default', function (): void {
    config()->set('reviews.photos.disk', null);

    expect(aboutOutput())->toContain('DEFAULT');
});

it('reports no banned words as NONE', function (): void {
    config()->set('reviews.moderation.banned_words', []);

    expect(aboutOutput())->toContain('NONE');
});

it('reports a disabled facade alias', function (): void {
    config()->set('reviews.register_facade_alias', false);

    expect(aboutOutput())->toContain('DISABLED');
});

it('reports disabled photos without limits', function (): void {
    config()->set('reviews.photos.enabled', false);

    $rendered = aboutOutput();

    expect($rendered)->toContain('Photo limits')
        ->and($rendered)->toContain('N/A');
});

it('reports an unlimited photo gallery with no size cap', function (): void {
    config()->set('reviews.photos.max', 0);
    config()->set('reviews.photos.max_file_size', 0);

    $rendered = aboutOutput();

    expect($rendered)->toContain('unlimited')
        ->and($rendered)->toContain('no size cap');
});
