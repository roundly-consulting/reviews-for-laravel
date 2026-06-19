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

            $table->string('title')->nullable();
            $table->text('content');
            $table->json('meta')->nullable();

            $table->timestamps();
            $table->softDeletes();
        });
    }
};
