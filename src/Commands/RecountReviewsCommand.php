<?php

declare(strict_types=1);

namespace RoundlyConsulting\Reviews\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;
use RoundlyConsulting\Reviews\Concerns\MaintainsReviewAggregates;

/**
 * Rebuilds the denormalized review aggregate columns (reviews_count,
 * reviews_avg) for every record of a reviewable model from scratch.
 */
final class RecountReviewsCommand extends Command
{
    protected $signature = 'reviews:recount {model : The fully-qualified reviewable model class}';

    protected $description = 'Rebuild cached review aggregate columns for a reviewable model';

    public function handle(): int
    {
        $argument = $this->argument('model');
        $model = is_string($argument) ? $argument : '';

        if (! class_exists($model) || ! is_subclass_of($model, Model::class)) {
            $this->components->error("[{$model}] is not a valid Eloquent model.");

            return self::FAILURE;
        }

        if (! in_array(MaintainsReviewAggregates::class, class_uses_recursive($model), true)) {
            $this->components->error("[{$model}] does not use the MaintainsReviewAggregates trait.");

            return self::FAILURE;
        }

        $count = 0;

        $model::query()->chunkById(100, function ($records) use (&$count): void {
            foreach ($records as $record) {
                if (method_exists($record, 'recountReviews')) {
                    $record->recountReviews();
                }
                $count++;
            }
        });

        $this->components->info("Recounted reviews for {$count} [{$model}] record(s).");

        return self::SUCCESS;
    }
}
