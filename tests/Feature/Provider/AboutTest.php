<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;

/**
 * `php artisan about --only=reviews` reports switches, bounds and presence — never the moderation
 * blocklist itself (printing it hands anyone reading the output the exact word list the filter
 * screens for) and never the photos disk by name.
 */
function aboutOutput(): string
{
    Artisan::call('about', ['--only' => 'reviews']);

    return Artisan::output();
}

it('contributes a reviews section to about', function (): void {
    $rendered = aboutOutput();

    expect($rendered)->toContain('Review model')
        ->and($rendered)->toContain('Rating scale')
        ->and($rendered)->toContain('1-5')
        ->and($rendered)->toContain('Cached aggregates')
        ->and($rendered)->toContain('Photos');
});

it('reports the moderation blocklist as a count, never the terms', function (): void {
    config()->set('reviews.moderation.banned_words', ['scandalous', 'unspeakable']);

    $rendered = aboutOutput();

    // Assert the capture is non-empty FIRST — an empty capture turns every negative below into a
    // vacuous pass (the purchases near-miss).
    expect($rendered)->toContain('Banned words')
        ->and($rendered)->toContain('2 term(s)')
        ->and($rendered)->not->toContain('scandalous')
        ->and($rendered)->not->toContain('unspeakable');
});

it('reports the photos disk as presence, never by name', function (): void {
    config()->set('reviews.photos.disk', 'super-secret-bucket');

    $rendered = aboutOutput();

    expect($rendered)->toContain('Photo disk')
        ->and($rendered)->toContain('SET')
        ->and($rendered)->not->toContain('super-secret-bucket');
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
