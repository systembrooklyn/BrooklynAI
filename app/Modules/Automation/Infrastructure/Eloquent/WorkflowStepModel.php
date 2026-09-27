<?php

namespace App\Modules\Automation\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;

class WorkflowStepModel extends Model
{
    protected $table = 'workflow_steps';

    protected $fillable = [
        'workflow_id',
        'position',
        'integration_key',
        'action_key',
        'connection_id',
        'config',
    ];

    protected $casts = [
        'config' => 'array',
        'position' => 'integer',
    ];
}
