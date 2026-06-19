<?php

declare(strict_types=1);

use RoundlyConsulting\Reviews\Testing\ReviewExpectations;
use RoundlyConsulting\Reviews\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

ReviewExpectations::register();
