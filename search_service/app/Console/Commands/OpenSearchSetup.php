<?php

namespace App\Console\Commands;

use App\Services\SearchIndexService;
use Illuminate\Console\Command;

class OpenSearchSetup extends Command
{
    protected $signature = 'opensearch:setup
                            {--recreate : Delete existing products/categories indices (and aliases) then create fresh}
                            {--force : Skip confirmation when using --recreate}';

    protected $description = 'Create OpenSearch indices used by search_service (with nested attributes + synonym analyzers)';

    public function handle(SearchIndexService $index): int
    {
        if ($this->option('recreate')) {
            if (! $this->option('force') && ! $this->confirm('This will DELETE products/categories OpenSearch data and recreate empty indices. Continue?', true)) {
                $this->warn('Aborted.');

                return self::SUCCESS;
            }

            $index->recreateIndices();
            $this->info('OpenSearch indices recreated (empty). Re-publish products from catalog to refill.');

            return self::SUCCESS;
        }

        $index->ensureIndices();
        $this->info('OpenSearch indices are ready.');

        return self::SUCCESS;
    }
}
