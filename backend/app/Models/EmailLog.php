<?php
namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;

class EmailLog extends Model
{
    protected $connection = 'mongodb'; // Указываем подключение к Mongo
    protected $collection = 'email_logs';

    protected $fillable = [
        'message_id',
        'email',
        'payload',
        'status',
        'retries',       
        'moved_to_dlq',  // true / false
        'error_message',
        'name',
    ];
}