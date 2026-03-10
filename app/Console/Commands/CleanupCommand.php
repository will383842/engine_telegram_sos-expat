<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Jobs\CleanupExpiredJob;
use Illuminate\Console\Command;

class CleanupCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'telegram:cleanup';

    /**
     * The console command description.
     */
    protected $description = 'Clean up expired data (onboarding links, confirmations, queue)';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->info('Running cleanup...');

        // Dispatch synchronously so we get the result immediately
        $job = new CleanupExpiredJob;
        $counts = $job->handle();

        $this->table(
            ['Category', 'Cleaned'],
            collect($counts)->map(fn ($count, $category) => [$category, $count])->toArray()
        );

        $total = array_sum($counts);
        $this->info("Cleanup complete. Total records processed: {$total}");

        return self::SUCCESS;
    }
}
