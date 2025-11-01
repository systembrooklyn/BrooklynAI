<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class FacebookAccount extends Model
{
    protected $fillable = ['facebook_user_id', 'access_token', 'token_expires_at'];
}