<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

#[Signature('app:cleanup-abandoned-announcement-chunks {--minutes=60 : Delete chunk directories older than this}')]
#[Description('Deletes leftover chunks/{uuid} directories from video uploads that were never finalized or reverted (closed tab, network drop).')]
class CleanupAbandonedAnnouncementChunks extends Command
{
    public function handle(): int
    {
        $disk = Storage::disk('local');
        $thresholdSeconds = ((int) $this->option('minutes')) * 60;
        $now = time();

        $deleted = 0;

        foreach ($disk->directories('announcement-chunks/chunks') as $directory) {
            $realPath = $disk->path($directory);
            $age = $now - (is_dir($realPath) ? filemtime($realPath) : $now);

            if ($age >= $thresholdSeconds) {
                $disk->deleteDirectory($directory);
                $deleted++;
            }
        }

        $this->info("Deleted {$deleted} abandoned chunk director".($deleted === 1 ? 'y' : 'ies').'.');

        return self::SUCCESS;
    }
}
