<?php

namespace App\Modules\Execution\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;

class ExecutionModel extends Model
{
    protected $table = 'executions';

    protected $fillable = [
        'workflow_id',
        'user_id',
        'status',
        'trigger_source',
        'trigger_payload',
        'workflow_snapshot',
        'idempotency_key',
        'error_message',
        'retry_attempts',
        'retry_of_id',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'trigger_payload' => 'array',
        'workflow_snapshot' => 'array',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];
}
