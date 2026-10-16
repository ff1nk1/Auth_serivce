<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::connection('mongodb')->table('email_logs', function ($collection) {
            $collection->index('message_id', [
                'unique' => true,
                'sparse' => true,
            ]);
        });
    }

    public function down(): void
    {
        Schema::connection('mongodb')->table('email_logs', function ($collection) {
            $collection->dropIndexIfExists('message_id_1');
        });
    }
};
