<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('review_votes', function (Blueprint $table): void {
            $table->id();

            $table->foreignId('review_id')->constrained('reviews')->cascadeOnDelete();
            $table->nullableMorphs('voter');

            // true = helpful, false = unhelpful.
            $table->boolean('helpful')->default(true);

            $table->timestamps();

            // One vote per voter per review; re-voting flips the existing row.
            $table->unique(['review_id', 'voter_type', 'voter_id']);
        });
    }
};
