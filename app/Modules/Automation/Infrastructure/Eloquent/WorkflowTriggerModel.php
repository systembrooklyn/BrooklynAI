<?php

namespace App\Modules\Automation\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;

class WorkflowTriggerModel extends Model
{
    protected $table = 'workflow_triggers';

    protected $fillable = [
        'workflow_id',
        'integration_key',
        'trigger_key',
        'connection_id',
        'strategy',
        'config',
        'interval_minutes',
        'poll_cursor',
        'next_poll_at',
    ];

    protected $casts = [
        'config' => 'array',
        'interval_minutes' => 'integer',
        'poll_cursor' => 'integer',
        'next_poll_at' => 'datetime',
    ];
}
