<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('entities', function (Blueprint $table): void {
            $table->id();
        });

        Schema::create('catalogs', function (Blueprint $table): void {
            $table->id();
            $table->unsignedInteger('reviews_count')->default(0);
            $table->decimal('reviews_avg', 8, 4)->nullable();
        });
    }
};
