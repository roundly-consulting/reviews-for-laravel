<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\DB;
use RoundlyConsulting\PackageToolkit\Exceptions\InvalidConfigurationException;
use RoundlyConsulting\Reviews\Facades\Reviews;
use RoundlyConsulting\Reviews\Http\Resources\ReviewableResource;
use RoundlyConsulting\Reviews\Http\Resources\ReviewAuthorResource;
use RoundlyConsulting\Reviews\Http\Resources\ReviewCollection;
use RoundlyConsulting\Reviews\Http\Resources\ReviewResource;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Tests\Entity;
use RoundlyConsulting\Reviews\Tests\Fixtures\AbstractReferenceResource;
use RoundlyConsulting\Reviews\Tests\Fixtures\AuthorCardResource;
use RoundlyConsulting\Reviews\Tests\Fixtures\ReviewedItemResource;
use RoundlyConsulting\Reviews\Tests\Product;

/*
 * Owner rule, 2026-10-18: nothing a client receives carries a PHP class name. ReviewResource
 * rendered `author.type` / `reviewable.type` straight from the morph columns — `App\Models\User`
 * on a host without a morph map, i.e. the host's internal class layout in every API response.
 * Each related model now renders through its own swappable resource: `id` + the class basename.
 */

afterEach(function (): void {
    Relation::morphMap([], false);
});

function referencesJson(JsonResource $resource): string
{
    return (string) $resource->toResponse(Request::create('/'))->getContent();
}

/**
 * @return array<string, mixed>
 */
function referencesPayload(JsonResource $resource): array
{
    /** @var array{data: array<string, mixed>} $decoded */
    $decoded = json_decode(referencesJson($resource), true, flags: JSON_THROW_ON_ERROR);

    return $decoded['data'];
}

/**
 * Every string value in a decoded payload, at any depth.
 *
 * @param  array<array-key, mixed>  $payload
 * @return list<string>
 */
function referencesStrings(array $payload): array
{
    $strings = [];

    array_walk_recursive($payload, static function (mixed $value) use (&$strings): void {
        if (is_string($value)) {
            $strings[] = $value;
        }
    });

    return $strings;
}

/**
 * How many statements hit the `entities` table — every author and subject in this suite lives
 * there (Product shares the table) — while `$render` runs.
 */
function referencesLookups(Closure $render): int
{
    $count = 0;

    DB::listen(static function (QueryExecuted $query) use (&$count): void {
        if (str_contains($query->sql, 'entities')) {
            $count++;
        }
    });

    $render();

    return $count;
}

function referencesReview(?Entity $author = null, ?Product $subject = null): Review
{
    return Review::factory()
        ->approved()
        ->rating(4)
        ->forReviewable($subject ?? Product::query()->create())
        ->byAuthor($author ?? Entity::query()->create())
        ->create(['title' => 'Solid', 'content' => 'Works well']);
}

it('renders author and reviewable as id + class basename, never a class name', function (): void {
    $author = Entity::query()->create();
    $product = Product::query()->create();
    $review = referencesReview($author, $product);
    Reviews::respond($review, Entity::query()->create(), 'Thanks!');

    $payload = referencesPayload(new ReviewResource(Review::query()->with('responses')->findOrFail($review->getKey())));

    expect($payload['author'])->toBe(['id' => $author->getKey(), 'type' => 'Entity'])
        ->and($payload['reviewable'])->toBe(['id' => $product->getKey(), 'type' => 'Product'])
        ->and($payload['responses'][0]['author']['type'])->toBe('Entity')
        ->and($payload['responses'][0]['reviewable'])->toBe(['id' => $product->getKey(), 'type' => 'Product']);

    // No backslash-qualified class name anywhere in what the client receives.
    foreach (referencesStrings($payload) as $string) {
        expect($string)->not->toContain('\\');
    }
});

it('renders the class basename under a morph map too, never the alias', function (): void {
    Relation::morphMap(['member' => Entity::class, 'product' => Product::class]);

    $review = referencesReview();

    expect($review->author_type)->toBe('member')
        ->and(referencesPayload(new ReviewResource($review)))
        ->author->type->toBe('Entity')
        ->reviewable->type->toBe('Product');
});

it('renders null for a deleted author or subject, keeping both keys', function (): void {
    $review = referencesReview();
    Entity::query()->whereKey([$review->author_id, $review->reviewable_id])->delete();

    $payload = referencesPayload(new ReviewResource($review->fresh() ?? $review));

    expect($payload)->toHaveKeys(['author', 'reviewable'])
        ->and($payload['author'])->toBeNull()
        ->and($payload['reviewable'])->toBeNull();
});

it('renders the related model through the default reference resources', function (): void {
    $entity = Entity::query()->create();
    $product = Product::query()->create();

    expect((new ReviewAuthorResource($entity))->resolve())->toBe(['id' => $entity->getKey(), 'type' => 'Entity'])
        ->and((new ReviewableResource($product))->resolve())->toBe(['id' => $product->getKey(), 'type' => 'Product']);
});

it('renders through the resources a host configures', function (): void {
    config()->set('reviews.resources.author', AuthorCardResource::class);
    config()->set('reviews.resources.reviewable', ReviewedItemResource::class);

    $review = referencesReview();
    $expected = [
        'author' => ['id' => $review->author_id, 'name' => 'Member #'.$review->author_id, 'avatar' => null],
        'reviewable' => ['id' => $review->reviewable_id, 'title' => 'Item #'.$review->reviewable_id],
    ];

    /** @var array{data: list<array<string, mixed>>} $collection */
    $collection = json_decode(referencesJson(new ReviewCollection(Review::query()->get())), true, flags: JSON_THROW_ON_ERROR);

    expect(referencesPayload(new ReviewResource($review)))->toMatchArray($expected)
        ->and($collection['data'][0])->toMatchArray($expected)
        ->and(ReviewResource::authorOf($review, Request::create('/')))->toBe($expected['author'])
        ->and(ReviewResource::reviewableOf($review, Request::create('/')))->toBe($expected['reviewable']);
});

it('hands PHP callers of toArray() plain arrays, resolved with the same request', function (): void {
    $review = referencesReview();
    $request = Request::create('/');

    $array = (new ReviewResource($review))->toArray($request);

    expect($array['author'])->toBe(['id' => $review->author_id, 'type' => 'Entity'])
        ->and($array['reviewable'])->toBe(['id' => $review->reviewable_id, 'type' => 'Product'])
        ->and(ReviewResource::authorOf($review, $request))->toBe($array['author'])
        ->and(ReviewResource::reviewableOf($review, $request))->toBe($array['reviewable']);
});

it('falls back to the default resource when the setting is not set', function (mixed $value): void {
    config()->set('reviews.resources.author', $value);
    config()->set('reviews.resources.reviewable', $value);

    $review = referencesReview();

    expect(ReviewResource::authorOf($review, Request::create('/')))->toBe(['id' => $review->author_id, 'type' => 'Entity'])
        ->and(ReviewResource::reviewableOf($review, Request::create('/')))->toBe(['id' => $review->reviewable_id, 'type' => 'Product'])
        ->and(referencesPayload(new ReviewResource($review)))
        ->author->toBe(['id' => $review->author_id, 'type' => 'Entity']);
})->with([
    'null' => [null],
    'empty' => [''],
    'whitespace' => ['   '],
]);

it('refuses a value that is not a JsonResource subclass, naming the key', function (string $role, mixed $value, string $given): void {
    config()->set("reviews.resources.{$role}", $value);

    $review = referencesReview();

    expect(fn () => referencesJson(new ReviewResource($review)))->toThrow(
        InvalidConfigurationException::class,
        "Configuration value [reviews.resources.{$role}] must be a class-string of [".JsonResource::class."], [{$given}] given.",
    );
})->with([
    'author: a model' => ['author', Entity::class, Entity::class],
    'author: JsonResource itself' => ['author', JsonResource::class, JsonResource::class],
    'author: an abstract resource' => ['author', AbstractReferenceResource::class, AbstractReferenceResource::class],
    'reviewable: an abstract resource' => ['reviewable', AbstractReferenceResource::class, AbstractReferenceResource::class],
    'author: a missing class' => ['author', 'App\\Http\\Resources\\Missing', 'App\\Http\\Resources\\Missing'],
    'author: an array' => ['author', [ReviewAuthorResource::class], 'array'],
    'reviewable: a model' => ['reviewable', Product::class, Product::class],
    'reviewable: an integer' => ['reviewable', 1, '1'],
]);

it('loads authors and subjects in one query per type for a whole ReviewCollection', function (): void {
    foreach (range(1, 4) as $ignored) {
        $review = referencesReview();
        Reviews::respond($review, Entity::query()->create(), 'Thanks!');
        Reviews::respond($review, Entity::query()->create(), 'Thanks again!');
    }

    $reviews = Review::query()->whereNull('parent_id')->with('responses')->get();
    $payload = [];

    // Entity authors + Product subjects: two morph types, two lookups — not 4 × 3 × 2.
    expect(referencesLookups(function () use ($reviews, &$payload): void {
        $payload = json_decode(referencesJson(new ReviewCollection($reviews)), true, flags: JSON_THROW_ON_ERROR);
    }))->toBe(2)
        ->and($payload['data'])->toHaveCount(4)
        ->and($payload['data'][3]['responses'])->toHaveCount(2)
        ->and($payload['data'][3]['responses'][1]['author']['type'])->toBe('Entity');
});

it('loads a paginated ReviewCollection in one query per type', function (): void {
    foreach (range(1, 3) as $ignored) {
        referencesReview();
    }

    $page = Review::query()->paginate(2);

    expect(referencesLookups(fn () => referencesJson(new ReviewCollection($page))))->toBe(2);
});

it('loads a single review\'s responses in one query per type', function (): void {
    $review = referencesReview();

    foreach (range(1, 3) as $ignored) {
        Reviews::respond($review, Entity::query()->create(), 'Thanks!');
    }

    $review = Review::query()->with(['author', 'reviewable', 'responses'])->findOrFail($review->getKey());

    expect(referencesLookups(fn () => referencesJson(new ReviewResource($review))))->toBe(2);
});

it('leaves relations already loaded alone', function (): void {
    referencesReview();
    referencesReview();

    $reviews = Review::query()->with(['author', 'reviewable'])->get();

    expect(referencesLookups(fn () => referencesJson(new ReviewCollection($reviews))))->toBe(0);
});
