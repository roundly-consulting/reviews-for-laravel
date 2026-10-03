<?php

declare(strict_types=1);

use Illuminate\Foundation\AliasLoader;
use RoundlyConsulting\Reviews\Facades\Reviews;
use RoundlyConsulting\Reviews\ReviewsServiceProvider;

/**
 * The alias is declared through the toolkit's `hasFacadeAlias()`, so it is registered in
 * `register()` (not `boot()`) and the CONFIG VALUE decides: an explicit null or a false spelling
 * (false/0/off/no) skips it, a non-empty string renames it, and true, an absent key or a blank
 * value (`REVIEWS_REGISTER_FACADE_ALIAS=` — not set) falls back to the facade's base name.
 */
function registerProvider(): void
{
    AliasLoader::getInstance()->setAliases([]);

    (new ReviewsServiceProvider(app()))->register();
}

it('skips alias registration when disabled', function (mixed $value): void {
    config()->set('reviews.register_facade_alias', $value);

    registerProvider();

    expect(AliasLoader::getInstance()->getAliases())->not->toHaveKey('Reviews');
})->with(['false' => [false], 'null' => [null], 'zero' => ['0'], 'off' => ['off'], 'no' => ['no'], 'false word' => ['false']]);

it('registers the alias when enabled', function (): void {
    config()->set('reviews.register_facade_alias', true);

    registerProvider();

    expect(AliasLoader::getInstance()->getAliases())->toHaveKey('Reviews', Reviews::class);
});

it('renames the alias when the config names one', function (): void {
    config()->set('reviews.register_facade_alias', 'Ratings');

    registerProvider();

    expect(AliasLoader::getInstance()->getAliases())
        ->toHaveKey('Ratings', Reviews::class)
        ->not->toHaveKey('Reviews');
});

it('registers the default alias for a blank value, which is not set', function (string $blank): void {
    config()->set('reviews.register_facade_alias', $blank);

    registerProvider();

    expect(AliasLoader::getInstance()->getAliases())->toHaveKey('Reviews', Reviews::class);
})->with(['empty' => [''], 'whitespace' => [' ']]);
