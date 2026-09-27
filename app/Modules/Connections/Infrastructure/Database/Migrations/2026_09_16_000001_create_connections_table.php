<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('connections', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('external_account_id', 191);
            $table->string('email')->nullable();
            $table->string('display_name')->nullable();
            $table->text('access_token')->nullable();
            $table->text('refresh_token')->nullable();
            $table->timestamp('token_expires_at')->nullable();
            $table->json('scopes');
            $table->string('status', 32)->default('active');
            $table->text('last_error_message')->nullable();
            $table->timestamps();

            $table->unique(
                ['user_id', 'provider', 'external_account_id'],
                'connections_user_provider_account_unique'
            );
            $table->index(['provider', 'status'], 'connections_provider_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('connections');
    }
};
