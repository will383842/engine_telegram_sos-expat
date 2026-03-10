<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Data\DefaultTemplates;
use App\Models\NotificationTemplate;
use Illuminate\Console\Command;

class SeedTemplatesCommand extends Command
{
    /**
     * The name and signature of the console command.
     */
    protected $signature = 'telegram:seed-templates {--force : Overwrite existing templates}';

    /**
     * The console command description.
     */
    protected $description = 'Seed default notification templates for all events and languages';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $force = $this->option('force');
        $templates = DefaultTemplates::all();

        $created = 0;
        $skipped = 0;
        $updated = 0;

        $this->info('Seeding notification templates...');
        $this->newLine();

        $bar = $this->output->createProgressBar(count($templates));
        $bar->start();

        foreach ($templates as $template) {
            $existing = NotificationTemplate::where('event_type', $template['event_type'])
                ->where('language', $template['language'])
                ->first();

            if ($existing) {
                if ($force && ! $existing->is_custom) {
                    $existing->update(['template' => $template['template']]);
                    $updated++;
                } else {
                    $skipped++;
                }
            } else {
                NotificationTemplate::create([
                    'event_type' => $template['event_type'],
                    'language' => $template['language'],
                    'template' => $template['template'],
                    'is_custom' => false,
                ]);
                $created++;
            }

            $bar->advance();
        }

        $bar->finish();
        $this->newLine(2);

        $this->table(
            ['Action', 'Count'],
            [
                ['Created', $created],
                ['Updated (--force)', $updated],
                ['Skipped (already exists)', $skipped],
            ]
        );

        $total = $created + $updated;
        $this->info("Done! {$total} template(s) created/updated, {$skipped} skipped.");

        if ($skipped > 0 && ! $force) {
            $this->warn('Use --force to overwrite existing non-custom templates.');
        }

        return self::SUCCESS;
    }
}
