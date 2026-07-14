<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Event;
use RoundlyConsulting\Reviews\Events\ReviewApproved;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\ReviewsServiceProvider;
use RoundlyConsulting\Reviews\Tests\TenantReview;

/**
 * The provider's config-driven boot wiring. Eloquent keys model events by the CONCRETE class, so a
 * host that swaps `reviews.model` must get the aggregate observer and the photo-purge hook on ITS
 * model — not on the packaged one, where they would never fire.
 */
function bootProvider(): void
{
    // The toolkit builds the package declaration in register(), so a provider instantiated by hand
    // must register() before it boots.
    $provider = new ReviewsServiceProvider(app());

    $provider->register();
    $provider->boot();
}

it('registers the aggregate observer on the configured model when caching is on', function (): void {
    config()->set('reviews.cache_aggregates', true);
    config()->set('reviews.model', TenantReview::class);

    bootProvider();

    expect(Event::hasListeners('eloquent.saved: '.TenantReview::class))->toBeTrue()
        ->and(Event::hasListeners('eloquent.saved: '.Review::class))->toBeFalse();
});

it('leaves the aggregate observer unregistered when caching is off', function (): void {
    config()->set('reviews.cache_aggregates', false);

    bootProvider();

    expect(Event::hasListeners('eloquent.saved: '.Review::class))->toBeFalse();
});

it('registers the photo hooks on the configured model when photos are on', function (): void {
    config()->set('reviews.photos.enabled', true);
    config()->set('reviews.model', TenantReview::class);

    bootProvider();

    expect(Event::hasListeners('eloquent.forceDeleted: '.TenantReview::class))->toBeTrue()
        ->and(Event::hasListeners(ReviewApproved::class))->toBeTrue();
});
