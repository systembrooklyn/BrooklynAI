<?php

namespace App\Modules\Execution\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;

class ExecutionStepModel extends Model
{
    protected $table = 'execution_steps';

    protected $fillable = [
        'execution_id',
        'position',
        'step_snapshot',
        'status',
        'input',
        'output',
        'error_message',
        'started_at',
        'finished_at',
    ];

    protected $casts = [
        'step_snapshot' => 'array',
        'input' => 'array',
        'output' => 'array',
        'position' => 'integer',
        'started_at' => 'datetime',
        'finished_at' => 'datetime',
    ];
}
