<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Reviews\Support\ReviewModel;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_votes', function (Blueprint $table): void {
            $table->id();

            // Resolved from `reviews.model`, not hard-coded: a host that swaps in its own model on
            // its own table needs the constraint to target that table, or its votes can never
            // satisfy it on any engine that enforces foreign keys.
            $table->foreignId('review_id')->constrained(ReviewModel::table())->cascadeOnDelete();
            $table->nullableMorphs('voter');

            // true = helpful, false = unhelpful.
            $table->boolean('helpful')->default(true);

            $table->timestamps();

            // One vote per voter per review; re-voting flips the existing row.
            $table->unique(['review_id', 'voter_type', 'voter_id']);
        });
    }
};
