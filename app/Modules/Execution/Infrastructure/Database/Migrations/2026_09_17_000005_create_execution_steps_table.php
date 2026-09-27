<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('execution_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('execution_id')->constrained('executions')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->json('step_snapshot');
            $table->string('status', 24)->default('pending');
            $table->json('input')->nullable();
            $table->json('output')->nullable();
            $table->text('error_message')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();

            $table->unique(['execution_id', 'position'], 'execution_steps_execution_position_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('execution_steps');
    }
};
