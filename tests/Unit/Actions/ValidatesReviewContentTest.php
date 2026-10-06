<?php

declare(strict_types=1);

use RoundlyConsulting\Reviews\Actions\ValidatesReviewContent;
use RoundlyConsulting\Reviews\Exceptions\InvalidReviewException;

beforeEach(function (): void {
    $this->validate = new ValidatesReviewContent;
});

it('passes a rated review with no content', function (): void {
    $this->validate->execute(4, null);
})->throwsNoExceptions();

it('passes a rated review with blank content', function (): void {
    $this->validate->execute(4, '   ');
})->throwsNoExceptions();

it('passes a rating-less review with content', function (): void {
    $this->validate->execute(null, ' Great ');
})->throwsNoExceptions();

it('rejects a review with neither a rating nor content', function (?string $content): void {
    $this->validate->execute(null, $content);
})->with([null, '', '   ', "\n\t", "\u{00A0}"])->throws(InvalidReviewException::class);
