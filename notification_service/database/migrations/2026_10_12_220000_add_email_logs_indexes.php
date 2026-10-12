<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mongodb')->table('email_logs', function ($collection) {
            $collection->index(['created_at' => -1]);
            $collection->index('email');
            $collection->index(['status' => 1, 'created_at' => -1]);
        });
    }

    public function down(): void
    {
        Schema::connection('mongodb')->table('email_logs', function ($collection) {
            $collection->dropIndexIfExists(['created_at' => -1]);
            $collection->dropIndexIfExists('email');
            $collection->dropIndexIfExists(['status' => 1, 'created_at' => -1]);
        });
    }
};
