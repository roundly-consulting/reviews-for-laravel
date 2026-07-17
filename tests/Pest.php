<?php

declare(strict_types=1);

use RoundlyConsulting\Reviews\Testing\ReviewExpectations;
use RoundlyConsulting\Reviews\Tests\SwappedModelTestCase;
use RoundlyConsulting\Reviews\Tests\TestCase;

uses(TestCase::class)->in(__DIR__.'/Feature', __DIR__.'/Unit');

// Hosts that swap `reviews.model` do it in config, so the swap is in place before the package's
// migrations run. These tests boot that way, with foreign keys enforced.
uses(SwappedModelTestCase::class)->in(__DIR__.'/SwappedModel');

ReviewExpectations::register();
