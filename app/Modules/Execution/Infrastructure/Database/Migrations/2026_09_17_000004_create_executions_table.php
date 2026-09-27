<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('executions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')
                ->constrained('workflows')
                ->restrictOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('status', 24)->default('pending');
            $table->string('trigger_source', 24);
            $table->json('trigger_payload')->nullable();
            $table->json('workflow_snapshot');
            $table->string('idempotency_key', 191)->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique(['workflow_id', 'idempotency_key'], 'executions_workflow_idempotency_unique');
            $table->index(['workflow_id', 'status'], 'executions_workflow_status_index');
            $table->index(['user_id', 'status'], 'executions_user_status_index');
            $table->index(['workflow_id', 'created_at'], 'executions_workflow_created_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('executions');
    }
};
