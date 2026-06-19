<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Moderation;

use Illuminate\Support\Str;
use RoundlyConsulting\Reviews\Contracts\ReviewModerator;
use RoundlyConsulting\Reviews\DataTransferObjects\ModerationOutcome;
use RoundlyConsulting\Reviews\Models\Review;

/**
 * A native, dependency-free moderator that rejects a review when its title or
 * content contains any configured banned word (case-insensitive, whole-word).
 * Anything clean is left pending for the normal flow.
 */
final class WordListModerator implements ReviewModerator
{
    /** @var list<string> */
    private array $bannedWords;

    /**
     * @param  list<string>|null  $bannedWords  Defaults to config('reviews.moderation.banned_words').
     */
    public function __construct(?array $bannedWords = null)
    {
        if ($bannedWords === null) {
            /** @var list<string> $configured */
            $configured = config('reviews.moderation.banned_words', []);
            $bannedWords = $configured;
        }

        $this->bannedWords = array_values(array_map(
            static fn (string $word): string => Str::lower(trim($word)),
            array_filter($bannedWords, static fn (string $word): bool => trim($word) !== ''),
        ));
    }

    public function moderate(Review $review): ModerationOutcome
    {
        if ($this->bannedWords === []) {
            return ModerationOutcome::pending();
        }

        $haystack = Str::lower(trim(($review->title ?? '').' '.($review->content ?? '')));

        if ($haystack === '') {
            return ModerationOutcome::pending();
        }

        $words = preg_split('/\W+/', $haystack, flags: PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($this->bannedWords as $banned) {
            if (in_array($banned, $words, true)) {
                return ModerationOutcome::reject(
                    (string) trans('reviews::messages.review.banned_word'),
                );
            }
        }

        return ModerationOutcome::pending();
    }
}
