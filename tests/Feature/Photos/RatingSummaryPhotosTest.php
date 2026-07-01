<?php

declare(strict_types=1);

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RoundlyConsulting\Reviews\Facades\Reviews;
use RoundlyConsulting\Reviews\Tests\Entity;
use RoundlyConsulting\Reviews\Tests\Product;

beforeEach(function (): void {
    Storage::fake('public');
});

function reviewWithPhotos(Product $product, int $count, bool $approved): void
{
    $builder = Reviews::for($product)->by(Entity::create())->rating(5)->content('great');

    for ($i = 0; $i < $count; $i++) {
        $builder->withPhoto(UploadedFile::fake()->image("p{$i}.jpg", 400, 300));
    }

    if ($approved) {
        $builder->approved();
    }

    $builder->create();
}

it('counts photos across approved reviews only', function (): void {
    $product = Product::create();

    reviewWithPhotos($product, 2, approved: true);
    reviewWithPhotos($product, 3, approved: true);
    reviewWithPhotos($product, 4, approved: false); // pending — excluded

    $summary = $product->ratingSummary();

    expect($summary->photoCount)->toBe(5)
        ->and($summary->reviewsWithPhotos)->toBe(2)
        ->and(Reviews::photoCountFor($product))->toBe(5)
        ->and(Reviews::reviewsWithPhotosFor($product))->toBe(2);
});

it('ignores approved reviews that carry no photos', function (): void {
    $product = Product::create();

    Reviews::for($product)->by(Entity::create())->rating(5)->content('no photo')->approved()->create();
    reviewWithPhotos($product, 2, approved: true);

    $summary = $product->ratingSummary();

    expect($summary->photoCount)->toBe(2)
        ->and($summary->reviewsWithPhotos)->toBe(1);
});

it('reports zero photo counts when photos are disabled', function (): void {
    $product = Product::create();

    reviewWithPhotos($product, 2, approved: true);

    config()->set('reviews.photos.enabled', false);

    expect(Reviews::photoCountFor($product))->toBe(0)
        ->and(Reviews::reviewsWithPhotosFor($product))->toBe(0)
        ->and($product->ratingSummary()->photoCount)->toBe(0);
});
