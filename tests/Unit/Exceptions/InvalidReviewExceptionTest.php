<?php

declare(strict_types=1);

use RoundlyConsulting\Reviews\Exceptions\InvalidReviewException;
use RoundlyConsulting\Reviews\Exceptions\ReviewException;

it('builds an empty-review exception', function (): void {
    $exception = InvalidReviewException::empty();

    expect($exception)
        ->toBeInstanceOf(ReviewException::class)
        ->and($exception->getMessage())->toBe('A review must have either a rating or content.');
});

it('builds a duplicate-review exception', function (): void {
    $exception = InvalidReviewException::duplicate();

    expect($exception)
        ->toBeInstanceOf(ReviewException::class)
        ->and($exception->getMessage())->toBe('This author has already reviewed this subject.');
});

it('builds a too-many-photos exception with the right plural', function (): void {
    expect(InvalidReviewException::tooManyPhotos(1)->getMessage())->toBe('A review may have at most 1 photo.')
        ->and(InvalidReviewException::tooManyPhotos(3)->getMessage())->toBe('A review may have at most 3 photos.');

    app()->setLocale('sk');

    expect(InvalidReviewException::tooManyPhotos(1)->getMessage())->toBe('Recenzia môže mať najviac 1 fotografiu.')
        ->and(InvalidReviewException::tooManyPhotos(3)->getMessage())->toBe('Recenzia môže mať najviac 3 fotografie.')
        ->and(InvalidReviewException::tooManyPhotos(5)->getMessage())->toBe('Recenzia môže mať najviac 5 fotografií.');
});

it('renders a zero photo limit without stray whitespace', function (): void {
    expect(InvalidReviewException::tooManyPhotos(0)->getMessage())->toBe('A review may have at most 0 photos.');

    app()->setLocale('sk');

    expect(InvalidReviewException::tooManyPhotos(0)->getMessage())->toBe('Recenzia môže mať najviac 0 fotografií.');
});

it('builds a photos-disabled exception in the current locale', function (): void {
    expect(InvalidReviewException::photosDisabled()->getMessage())
        ->toBe('Review photos are disabled; enable reviews.photos.enabled to attach photos.');

    app()->setLocale('sk');

    expect(InvalidReviewException::photosDisabled()->getMessage())
        ->toBe('Fotografie v recenziách sú vypnuté; ak ich chcete pripájať, zapnite reviews.photos.enabled.');
});
