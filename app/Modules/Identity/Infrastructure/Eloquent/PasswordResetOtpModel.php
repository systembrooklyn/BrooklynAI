<?php

namespace App\Modules\Identity\Infrastructure\Eloquent;

use Illuminate\Database\Eloquent\Model;

final class PasswordResetOtpModel extends Model
{
    protected $table = 'password_reset_otps';

    protected $fillable = [
        'email',
        'code_hash',
        'attempts',
        'expires_at',
        'used_at',
    ];

    protected $casts = [
        'attempts'   => 'integer',
        'expires_at' => 'datetime',
        'used_at'    => 'datetime',
    ];
}
