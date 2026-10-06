<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Tests;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Reviews\Models\Review;

/**
 * A host review model on a connection of its own — `reviews.model` accepts any subclass, so its
 * rows can live in another database than the default connection (and media-library's table).
 */
class SideReview extends Review
{
    protected $connection = 'side';

    protected $table = 'reviews';

    /** Register the `side` connection (in-memory SQLite) and create the reviews table on it. */
    public static function createSideConnection(): void
    {
        config()->set('database.connections.side', [
            'driver' => 'sqlite',
            'database' => ':memory:',
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        Schema::connection('side')->create('reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('parent_id')->nullable()->constrained('reviews')->nullOnDelete();
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

    public static function dropSideConnection(): void
    {
        DB::purge('side');
    }
}
