<?php

declare(strict_types=1);

use App\Http\Resources\Reviews\ReviewCollection as PublishedReviewCollection;
use App\Http\Resources\Reviews\ReviewResource as PublishedReviewResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\ServiceProvider;
use RoundlyConsulting\Reviews\Facades\Reviews;
use RoundlyConsulting\Reviews\Http\Resources\ReviewCollection;
use RoundlyConsulting\Reviews\Http\Resources\ReviewResource;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\ReviewsServiceProvider;
use RoundlyConsulting\Reviews\Tests\Entity;

/*
 * `reviews-resources` used to copy the package's own classes into app/Http/Resources/Reviews,
 * namespace and all: under `app/` they broke PSR-4 and were never loaded, so editing them changed
 * nothing. The tag now publishes host-owned classes in `App\Http\Resources\Reviews`, rendering
 * exactly what the package resource renders until the host edits them.
 */

/**
 * The `reviews-resources` publish map, keyed by destination basename.
 *
 * @return array<string, array{from: string, to: string}>
 */
function publishedResources(): array
{
    $files = [];

    foreach (ServiceProvider::pathsToPublish(ReviewsServiceProvider::class, 'reviews-resources') as $from => $to) {
        $files[basename((string) $to)] = ['from' => (string) $from, 'to' => (string) $to];
    }

    return $files;
}

it('publishes each resource as its own file under app/Http/Resources/Reviews', function (): void {
    $files = publishedResources();

    expect(array_keys($files))->toEqualCanonicalizing(['ReviewResource.php', 'ReviewCollection.php']);

    foreach ($files as $file) {
        expect(dirname($file['to']))->toBe(app_path('Http/Resources/Reviews'))
            ->and(is_file($file['from']))->toBeTrue();
    }
});

it('publishes classes in the app namespace, not the package one', function (): void {
    foreach (publishedResources() as $name => $file) {
        $class = 'App\\Http\\Resources\\Reviews\\'.basename($name, '.php');
        $source = (string) file_get_contents($file['from']);

        expect($source)->toContain('namespace App\\Http\\Resources\\Reviews;')
            ->not->toContain('namespace RoundlyConsulting')
            ->toContain('class '.class_basename($class).' ');
    }
});

it('renders exactly what the package resource renders until the host edits it', function (): void {
    foreach (publishedResources() as $file) {
        require_once $file['from'];
    }

    $review = Reviews::for(Entity::create())->by(Entity::create())->rating(5)->content('Great')->approved()->create();
    Reviews::respond($review, Entity::create(), 'Thanks!');
    $review = Review::query()->with('responses')->findOrFail($review->getKey());
    $request = Request::create('/');

    // Rendered to JSON, so nested resources (the responses) are resolved too.
    $json = fn (JsonResource $resource): mixed => json_decode((string) $resource->toResponse($request)->getContent(), true);

    $collection = new PublishedReviewCollection(collect([$review]));

    expect($json(new PublishedReviewResource($review)))->toBe($json(new ReviewResource($review)))
        ->and($json(new PublishedReviewResource($review))['data']['responses'])->toHaveCount(1)
        ->and($collection->collects)->toBe(PublishedReviewResource::class)
        ->and($json($collection))->toBe($json(new ReviewCollection(collect([$review]))));
});
