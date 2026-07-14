<?php

declare(strict_types=1);

use Illuminate\Foundation\AliasLoader;
use RoundlyConsulting\Reviews\Facades\Reviews;
use RoundlyConsulting\Reviews\ReviewsServiceProvider;

/**
 * The alias is declared through the toolkit's `hasFacadeAlias()`, so it is registered in
 * `register()` (not `boot()`) and the CONFIG VALUE decides: false/null/'' skip it, a non-empty
 * string renames it, and true (or an absent key) falls back to the facade's base name.
 */
function registerProvider(): void
{
    AliasLoader::getInstance()->setAliases([]);

    (new ReviewsServiceProvider(app()))->register();
}

it('skips alias registration when disabled', function (): void {
    config()->set('reviews.register_facade_alias', false);

    registerProvider();

    expect(AliasLoader::getInstance()->getAliases())->not->toHaveKey('Reviews');
});

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

it('skips alias registration on an empty alias name', function (): void {
    config()->set('reviews.register_facade_alias', '');

    registerProvider();

    expect(AliasLoader::getInstance()->getAliases())->not->toHaveKey('Reviews');
});
