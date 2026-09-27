<?php

namespace App\Modules\Connections\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;

class ConnectionModel extends Model
{
    protected $table = 'connections';

    protected $fillable = [
        'user_id',
        'provider',
        'external_account_id',
        'email',
        'display_name',
        'access_token',
        'refresh_token',
        'token_expires_at',
        'scopes',
        'status',
        'last_error_message',
    ];

    protected $casts = [
        'access_token' => 'encrypted',
        'refresh_token' => 'encrypted',
        'token_expires_at' => 'datetime',
        'scopes' => 'array',
    ];

    protected $hidden = [
        'access_token',
        'refresh_token',
        'last_error_message',
    ];
}
