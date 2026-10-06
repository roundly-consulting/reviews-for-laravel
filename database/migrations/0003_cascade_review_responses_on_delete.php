<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use RoundlyConsulting\Reviews\Support\ReviewModel;

/*
 * Owner responses are review rows whose `parent_id` points at the review they answer. 0001 shipped
 * that key as `nullOnDelete()`, so deleting a review in the database turned its responses into
 * top-level reviews. This switches the key to cascade on the configured `reviews.model` table —
 * on an install that already ran 0001 as much as on a new one. (The package also force-deletes
 * responses through Eloquent first; this covers deletes that never reach a model.)
 */
return new class extends Migration
{
    public function up(): void
    {
        $current = collect(Schema::getForeignKeys(ReviewModel::table()))
            ->first(fn (array $key): bool => $key['columns'] === ['parent_id']);

        if ($current !== null && $current['on_delete'] === 'cascade') {
            return;
        }

        Schema::table(ReviewModel::table(), function (Blueprint $table) use ($current): void {
            if ($current !== null) {
                // By its real name where the engine names keys (a host's own table may not use
                // Laravel's naming); by column on SQLite, whose keys have no name.
                $table->dropForeign($current['name'] ?? ['parent_id']);
            }

            $table->foreign('parent_id')->references('id')->on(ReviewModel::table())->cascadeOnDelete();
        });
    }
};
