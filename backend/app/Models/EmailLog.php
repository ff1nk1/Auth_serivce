<?php
namespace App\Models;

use MongoDB\Laravel\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory; 
class EmailLog extends Model
{
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
}