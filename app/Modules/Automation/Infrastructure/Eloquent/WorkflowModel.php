<?php

namespace App\Modules\Automation\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class WorkflowModel extends Model
{
    use SoftDeletes;

    protected $table = 'workflows';

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'status',
    ];
}
