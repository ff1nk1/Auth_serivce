<?php

namespace App\Console\Commands;

use App\Models\Conversation;
use Illuminate\Console\Command;

class CleanupConversationsCommand extends Command
{
    protected $signature = 'conversations:cleanup';

    protected $description = 'Delete expired conversation contexts (messages cascade)';

    public function handle(): int
    {
        $deleted = Conversation::query()
            ->where('expires_at', '<', now())
            ->delete();

        $this->info("Deleted {$deleted} expired conversation(s).");

        return self::SUCCESS;
    }
}
