<?php

namespace App\Console\Commands;

use App\Jobs\ScrapeSourceJob;
use App\Models\NewsSource;
use Illuminate\Console\Command;

class ScrapeNewsCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'news:scrape
                            {--source= : Slug of a specific source to scrape (optional)}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Dispatch scraping jobs for all active news sources (or a single source by slug).';

    /**
     * Execute the console command.
     *
     * Dispatches a ScrapeSourceJob for each active NewsSource.
     * If --source is provided, only that source is dispatched.
     * Exits with code 1 if --source is given but no matching active source is found.
     */
    public function handle(): int
    {
        $slug = $this->option('source');

        $query = NewsSource::where('is_active', true);

        if ($slug !== null) {
            $query->where('slug', $slug);
        }

        $sources = $query->get();

        if ($sources->isEmpty()) {
            if ($slug !== null) {
                $this->error("No active news source found with slug: {$slug}");
            } else {
                $this->warn('No active news sources found. Nothing to scrape.');
            }

            return self::FAILURE;
        }

        foreach ($sources as $source) {
            ScrapeSourceJob::dispatch($source);
            $this->line("  Dispatched scrape job for: <info>{$source->name}</info> [{$source->slug}]");
        }

        $this->info("Dispatched {$sources->count()} scrape job(s).");

        return self::SUCCESS;
    }
}
