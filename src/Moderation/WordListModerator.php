<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Moderation;

use Illuminate\Support\Str;
use Normalizer;
use RoundlyConsulting\Reviews\Contracts\ReviewModerator;
use RoundlyConsulting\Reviews\DataTransferObjects\ModerationOutcome;
use RoundlyConsulting\Reviews\Models\Review;

/**
 * A native, dependency-free moderator that rejects a review when its title or
 * content contains any configured banned word (case-insensitive, whole-word).
 * Anything clean is left pending for the normal flow.
 *
 * Matching is Unicode-aware: a word is a run of letters (with their combining
 * marks), digits and underscores in any script, so `idiót` or `дурак` match as
 * whole words and an accented letter never splits a word in two. Text and
 * banned words are lower-cased multibyte-safely and, where ext-intl is
 * available, normalised to NFC so a decomposed spelling matches too.
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
            static fn (string $word): string => self::normalize(trim($word)),
            array_filter($bannedWords, static fn (string $word): bool => trim($word) !== ''),
        ));
    }

    public function moderate(Review $review): ModerationOutcome
    {
        if ($this->bannedWords === []) {
            return ModerationOutcome::pending();
        }

        $haystack = self::normalize(trim(($review->title ?? '').' '.($review->content ?? '')));

        if ($haystack === '') {
            return ModerationOutcome::pending();
        }

        $words = preg_split('/[^\p{L}\p{M}\p{N}_]+/u', $haystack, flags: PREG_SPLIT_NO_EMPTY) ?: [];

        foreach ($this->bannedWords as $banned) {
            if (in_array($banned, $words, true)) {
                return ModerationOutcome::reject(
                    (string) trans('reviews::messages.review.banned_word'),
                );
            }
        }

        return ModerationOutcome::pending();
    }

    /**
     * Lower-cased, valid, NFC-composed UTF-8. Invalid byte sequences are replaced first: they
     * would make the `/u` split fail, and a failed split must never wave text through unscreened.
     */
    private static function normalize(string $text): string
    {
        $text = mb_scrub($text, 'UTF-8');

        if (class_exists(Normalizer::class)) {
            $text = Normalizer::normalize($text, Normalizer::FORM_C) ?: $text;
        }

        return Str::lower($text);
    }
}
