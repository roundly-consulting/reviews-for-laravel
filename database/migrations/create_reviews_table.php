<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reviews', function (Blueprint $table): void {
            $table->id();

            $table->nullableMorphs('reviewable');
            $table->nullableMorphs('author');

            // Stored as an integer so decimal/half-star input can be layered on
            // later (a wider column type) without a breaking change. Null = a
            // review with no score (text only).
            $table->unsignedTinyInteger('rating')->nullable()->index();
            $table->string('status')->default('pending')->index();

            $table->string('title')->nullable();
            // Nullable so a pure star rating (no text) is allowed.
            $table->text('content')->nullable();
            $table->json('meta')->nullable();

            $table->timestamp('approved_at')->nullable();

            $table->timestamps();
            $table->softDeletes();

            $table->index(['reviewable_type', 'reviewable_id', 'status']);
        });
    }
};
