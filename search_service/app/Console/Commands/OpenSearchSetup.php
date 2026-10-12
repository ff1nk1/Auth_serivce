<?php

namespace App\Console\Commands;

use App\Services\SearchIndexService;
use Illuminate\Console\Command;

class OpenSearchSetup extends Command
{
    protected $signature = 'opensearch:setup';

    protected $description = 'Create OpenSearch indices used by search_service';

    public function handle(SearchIndexService $index): int
    {
        $index->ensureIndices();
        $this->info('OpenSearch indices are ready.');

        return self::SUCCESS;
    }
}
