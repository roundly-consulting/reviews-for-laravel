<?php

declare(strict_types=1);

use RoundlyConsulting\Reviews\Enums\ModerationDecision;
use RoundlyConsulting\Reviews\Models\Review;
use RoundlyConsulting\Reviews\Moderation\WordListModerator;

/*
 * The text is split into words on every non-word run, but each banned entry was kept whole — so an
 * entry with a space or punctuation in it (`rip off`, `f*ck`) could never equal a word and never
 * fired. Entries are now split the same way and matched as a run of consecutive words.
 */

function moderateText(WordListModerator $moderator, string $content): ModerationDecision
{
    return $moderator->moderate(new Review(['content' => $content]))->decision;
}

it('rejects text containing a multi-word or punctuated banned entry', function (string $content): void {
    $moderator = new WordListModerator(['rip off', 'f*ck', 'scam']);

    expect(moderateText($moderator, $content))->toBe(ModerationDecision::Reject);
})->with([
    'spaced phrase' => ['What a rip off'],
    'phrase in other case and spacing' => ['What a  RIP   off!'],
    'punctuated word' => ['f*ck this'],
    'single word still' => ['total scam'],
]);

it('keeps phrase matching on whole words', function (string $content): void {
    $moderator = new WordListModerator(['rip off', 'f*ck']);

    expect(moderateText($moderator, $content))->toBe(ModerationDecision::Pending);
})->with([
    'joined' => ['what a ripoff'],
    'inside longer words' => ['a trip offer'],
    'words apart' => ['rip it off'],
    'reversed' => ['off rip'],
    'half a phrase' => ['rest in peace, rip'],
]);

it('drops an entry that has no word in it instead of matching everything', function (): void {
    $moderator = new WordListModerator(['***', ' - ']);

    expect(moderateText($moderator, 'A perfectly clean review'))->toBe(ModerationDecision::Pending);
});
