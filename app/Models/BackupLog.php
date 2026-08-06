<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class BackupLog extends Model
{
    protected $table = 'backup_logs';

    protected $fillable = [
        'file_name',
        'host',
        'database_name',
        'size_bytes',
        'status',
        'message',
        'created_by',
    ];
}
