<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Reviews\Models\Review;

/**
 * A host's own review model on its own table — the swap `config/reviews.php` explicitly invites
 * ("point this at your own model") for an app that already owns a `reviews` table.
 */
class TenantReview extends Review
{
    protected $table = 'tenant_reviews';

    /** The host's schema for the table this model lives on. */
    public static function createTable(): void
    {
        Schema::create('tenant_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('tenant_reviews')->nullOnDelete();
            $table->nullableMorphs('reviewable');
            $table->nullableMorphs('author');
            $table->unsignedTinyInteger('rating')->nullable();
            $table->string('status')->default('pending');
            $table->boolean('verified')->default(false);
            $table->string('title')->nullable();
            $table->text('content')->nullable();
            $table->json('meta')->nullable();
            $table->unsignedInteger('helpful_count')->default(0);
            $table->unsignedInteger('unhelpful_count')->default(0);
            $table->timestamp('approved_at')->nullable();
            $table->timestamps();
            $table->softDeletes();
        });
    }
}
