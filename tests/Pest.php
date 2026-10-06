<?php

declare(strict_types=1);

use RoundlyConsulting\Reviews\Testing\ReviewExpectations;
use RoundlyConsulting\Reviews\Tests\PhotosDisabledTestCase;
use RoundlyConsulting\Reviews\Tests\SwappedModelTestCase;
use RoundlyConsulting\Reviews\Tests\TestCase;

// ArchTest.php is added by FILE path — `uses()->in()` accepts one — because
// `swappableModelsAreNotFinal` reads the `reviews.model` / `reviews.vote_model` config
// defaults and so needs the app booted. An arch file is not automatically test-cased:
// passkeys' ArchTest was bound to nothing at all and its finality preset could never read a
// config default. It cannot join the SwappedModel bind below — that directory has swapped
// the defaults away, which is exactly what the preset asserts.
uses(TestCase::class)->in('ArchTest.php', __DIR__.'/Feature', __DIR__.'/Unit');

// Hosts that swap `reviews.model` / `reviews.vote_model` do it in config, so the swap is in
// place before the package's migrations run. These tests boot that way, with foreign keys
// enforced (the base case sets `foreign_key_constraints`, which this package's other suite
// never did).
uses(SwappedModelTestCase::class)->in(__DIR__.'/SwappedModel');

// Hosts that switch `reviews.photos.enabled` off do it in config, before the provider boots.
uses(PhotosDisabledTestCase::class)->in(__DIR__.'/PhotosDisabled');

ReviewExpectations::register();
