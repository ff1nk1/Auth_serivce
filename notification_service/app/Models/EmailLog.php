<?php

namespace App\Models;

use Database\Factories\EmailLogFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use MongoDB\Laravel\Eloquent\Model;

class EmailLog extends Model
{
    /** @use HasFactory<EmailLogFactory> */
    use HasFactory;

    protected $connection = 'mongodb';

    protected $collection = 'email_logs';

    protected $fillable = [
        'message_id',
        'email',
        'payload',
        'status',
        'retries',
        'moved_to_dlq',
        'error_message',
        'name',
        'time',
    ];

    protected static function newFactory(): EmailLogFactory
    {
        return EmailLogFactory::new();
    }
}
