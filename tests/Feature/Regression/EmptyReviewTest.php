<?php

declare(strict_types=1);

use RoundlyConsulting\Reviews\DataTransferObjects\UpdateReviewData;
use RoundlyConsulting\Reviews\Exceptions\InvalidReviewException;
use RoundlyConsulting\Reviews\Facades\Reviews;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Tests\Entity;

/*
 * "A review must have either a rating or content." The create guard only refused `''`, so
 * whitespace-only text with no rating went through, and an update could empty the content of a
 * rating-less review (update cannot clear a rating: null means unchanged).
 */

dataset('blank content', [
    'empty' => [''],
    'spaces' => ['   '],
    'newline and tab' => ["\n\t"],
]);

it('refuses a rating-less review whose content is blank', function (string $content): void {
    expect(fn () => Reviews::for(Entity::create())->by(Entity::create())->content($content)->create())
        ->toThrow(InvalidReviewException::class, (string) trans('reviews::messages.review.empty'));

    expect(Review::query()->count())->toBe(0);
})->with('blank content');

it('refuses an update that blanks a rating-less review and leaves the row alone', function (string $content): void {
    $review = Reviews::for(Entity::create())->by(Entity::create())->content('Lovely')->create();

    expect(fn () => Reviews::update($review, new UpdateReviewData(content: $content)))
        ->toThrow(InvalidReviewException::class, (string) trans('reviews::messages.review.empty'));

    expect($review->fresh()?->content)->toBe('Lovely');
})->with('blank content');

it('lets an update blank the content of a rated review, storing it as given', function (): void {
    $review = Reviews::for(Entity::create())->by(Entity::create())->rating(4)->content('Lovely')->create();

    Reviews::update($review, new UpdateReviewData(content: '  '));

    expect($review->fresh()?->content)->toBe('  ')
        ->and($review->fresh()?->rating)->toBe(4);
});
