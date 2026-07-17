<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use RoundlyConsulting\Reviews\Tests\TenantReview;

/**
 * The host's own reviews table, created BEFORE the package's migrations run — the ordering
 * a real install has when `config/reviews.php` points `model` at a host subclass.
 *
 * The schema itself stays on TenantReview so the fixture model and its table are declared
 * in one place.
 */
return new class extends Migration
{
    public function up(): void
    {
        TenantReview::createTable();
    }
};
