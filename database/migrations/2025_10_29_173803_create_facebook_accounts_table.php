<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up()
    {
        Schema::create('facebook_accounts', function (Blueprint $table) {
            $table->id();
            $table->string('facebook_user_id')->unique(); // e.g., "123456789"
            $table->text('access_token'); // long-lived (60 days)
            $table->timestamp('token_expires_at')->nullable(); // optional
            $table->timestamps();
        });
    }

    public function down()
    {
        Schema::dropIfExists('facebook_accounts');
    }
};