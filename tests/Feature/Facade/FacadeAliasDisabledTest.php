<?php

declare(strict_types=1);

use Illuminate\Foundation\AliasLoader;
use RoundlyConsulting\Reviews\ReviewsServiceProvider;

it('skips alias registration when disabled', function (): void {
    config()->set('reviews.register_facade_alias', false);

    AliasLoader::getInstance()->setAliases([]);

    (new ReviewsServiceProvider($this->app))->boot();

    expect(AliasLoader::getInstance()->getAliases())->not->toHaveKey('Reviews');
});

it('registers the alias when enabled', function (): void {
    config()->set('reviews.register_facade_alias', true);

    AliasLoader::getInstance()->setAliases([]);

    (new ReviewsServiceProvider($this->app))->boot();

    expect(AliasLoader::getInstance()->getAliases())->toHaveKey('Reviews');
});
