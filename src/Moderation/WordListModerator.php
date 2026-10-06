<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Moderation;

use Illuminate\Support\Str;
use Normalizer;
use RoundlyConsulting\Reviews\Contracts\ReviewModerator;
use RoundlyConsulting\Reviews\DataTransferObjects\ModerationOutcome;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Support\ReviewsConfig;

/**
 * A native, dependency-free moderator that rejects a review when its title or
 * content contains any configured banned word (case-insensitive, whole-word).
 * Anything clean is left pending for the normal flow.
 *
 * An entry is split into words exactly like the text, so a phrase or a punctuated
 * entry (`rip off`, `f*ck`) matches as a run of consecutive words: `What a rip
 * off` and `f*ck this` are rejected, `ripoff` and `trip offer` are not. An entry
 * with no word in it (`***`) is dropped rather than matching everything.
 *
 * Matching is Unicode-aware: a word is a run of letters (with their combining
 * marks), digits and underscores in any script, so `idiót` or `дурак` match as
 * whole words and an accented letter never splits a word in two. Text and
 * banned words are lower-cased multibyte-safely and, where ext-intl is
 * available, normalised to NFC so a decomposed spelling matches too.
 */
final class WordListModerator implements ReviewModerator
{
    /**
     * Each banned entry as the words it splits into.
     *
     * @var list<non-empty-list<string>>
     */
    private array $bannedPhrases = [];

    /**
     * @param  list<string>|null  $bannedWords  Defaults to config('reviews.moderation.banned_words').
     */
    public function __construct(?array $bannedWords = null)
    {
        if ($bannedWords === null) {
            $bannedWords = ReviewsConfig::bannedWords();
        }

        foreach ($bannedWords as $entry) {
            $words = self::words(self::normalize(trim($entry)));

            if ($words !== []) {
                $this->bannedPhrases[] = $words;
            }
        }
    }

    public function moderate(Review $review): ModerationOutcome
    {
        if ($this->bannedPhrases === []) {
            return ModerationOutcome::pending();
        }

        $haystack = self::normalize(trim(($review->title ?? '').' '.($review->content ?? '')));

        if ($haystack === '') {
            return ModerationOutcome::pending();
        }

        $words = self::words($haystack);

        foreach ($this->bannedPhrases as $banned) {
            if (self::containsPhrase($words, $banned)) {
                return ModerationOutcome::reject(
                    (string) trans('reviews::messages.review.banned_word'),
                );
            }
        }

        return ModerationOutcome::pending();
    }

    /**
     * The words of a normalised text: runs of letters (with their marks), digits and underscores.
     *
     * @return list<string>
     */
    private static function words(string $text): array
    {
        return preg_split('/[^\p{L}\p{M}\p{N}_]+/u', $text, flags: PREG_SPLIT_NO_EMPTY) ?: [];
    }

    /**
     * Whether `$phrase` occurs in `$words` as a run of consecutive words.
     *
     * @param  list<string>  $words
     * @param  non-empty-list<string>  $phrase
     */
    private static function containsPhrase(array $words, array $phrase): bool
    {
        $length = count($phrase);

        if ($length === 1) {
            return in_array($phrase[0], $words, true);
        }

        for ($start = 0, $last = count($words) - $length; $start <= $last; $start++) {
            if (array_slice($words, $start, $length) === $phrase) {
                return true;
            }
        }

        return false;
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
