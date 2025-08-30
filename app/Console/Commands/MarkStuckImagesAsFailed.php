<?php

namespace App\Console\Commands;

use App\Models\Image;
use Illuminate\Console\Command;

class MarkStuckImagesAsFailed extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'images:mark-failed 
                            {--hours=1 : Mark images stuck in processing/pending for more than X hours as failed}
                            {--dry-run : Show what would be marked as failed without actually updating}
                            {--all : Mark ALL processing/pending images as failed regardless of time}
                            {--yes : Skip confirmation and proceed}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Mark images that are stuck in processing or pending status as failed';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $hours = $this->option('hours');
        $dryRun = $this->option('dry-run');
        $all = $this->option('all');

        if ($all) {
            $this->info('Looking for ALL images in processing/pending status...');
        } else {
            $this->info("Looking for images stuck in processing/pending status for more than {$hours} hour(s)...");
        }

        // Find stuck images
        $query = Image::whereIn('status', ['processing', 'pending'])
            ->whereNull('generated_image_path');

        if (! $all) {
            $query->where('created_at', '<', now()->subHours($hours));
        }

        $stuckImages = $query->get();

        if ($stuckImages->isEmpty()) {
            $this->info('No stuck images found.');

            return Command::SUCCESS;
        }

        $this->info("Found {$stuckImages->count()} stuck image(s):");

        foreach ($stuckImages as $image) {
            $this->line("  - Image #{$image->id} (status: {$image->status}, created: {$image->created_at})");
        }

        if ($dryRun) {
            $this->warn('Dry run mode - no changes made.');

            return Command::SUCCESS;
        }

        // Skip confirmation if --yes is provided
        $shouldProceed = $this->option('yes') || $this->confirm('Do you want to mark these images as failed?');
        
        if ($shouldProceed) {
            $updated = 0;

            // Update each image individually to trigger model events and broadcasting
            foreach ($stuckImages as $image) {
                $image->update([
                    'status' => 'failed',
                    'error_message' => 'Job timed out or failed without proper error handling',
                ]);
                $updated++;
            }

            $this->info("Successfully marked {$updated} image(s) as failed.");
            $this->info('Users can now use the "Try Again" button to retry these images.');
            $this->info('The UI should update automatically via broadcasting.');
        } else {
            $this->info('Operation cancelled.');
        }

        return Command::SUCCESS;
    }
}
