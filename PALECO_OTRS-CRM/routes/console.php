<?php

use App\Models\AccomplishmentPhoto;
use App\Models\IdempotencyRecord;
use App\Models\TicketAccomplishment;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Facades\Storage;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Deletes accomplishment signatures and photos that no database row points to (e.g. after a reseed).
// Dry run by default; pass --force to delete.
Artisan::command('storage:purge-orphaned-evidence {--force : Delete the files instead of only listing them}', function () {
    $disk = Storage::disk(config('filesystems.default'));

    $referenced = TicketAccomplishment::query()->whereNotNull('signature_path')->pluck('signature_path')
        ->merge(AccomplishmentPhoto::query()->pluck('file_path'))
        ->flip();

    $orphans = collect($disk->allFiles('accomplishments/signatures'))
        ->merge($disk->allFiles('accomplishments/photos'))
        ->reject(fn (string $path) => $referenced->has($path))
        ->values();

    $this->info("Disk: ".config('filesystems.default')." · orphaned evidence files: {$orphans->count()}");

    if ($orphans->isEmpty()) {
        return;
    }

    if (! $this->option('force')) {
        $orphans->take(20)->each(fn (string $path) => $this->line("  {$path}"));
        $this->comment('Dry run only. Run again with --force to delete them.');

        return;
    }

    $orphans->chunk(100)->each(fn ($chunk) => $disk->delete($chunk->all()));
    $this->info("Deleted {$orphans->count()} orphaned files.");
})->purpose('Delete accomplishment signatures and photos that no database row references');

Schedule::command('model:prune', ['--model' => [IdempotencyRecord::class]])->daily();

