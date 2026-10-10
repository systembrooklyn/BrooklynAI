<?php

namespace App\Modules\Connections\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;

class OAuthStateModel extends Model
{
    protected $table = 'oauth_states';

    protected $fillable = [
        'state',
        'user_id',
        'provider',
        'platform',
        'scopes_requested',
        'consumed_at',
        'expires_at',
    ];

    protected $casts = [
        'scopes_requested' => 'array',
        'consumed_at' => 'datetime',
        'expires_at' => 'datetime',
    ];
}
