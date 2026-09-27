<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_triggers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained('workflows')->cascadeOnDelete();
            $table->string('integration_key', 64);
            $table->string('trigger_key', 64);
            $table->foreignId('connection_id')->nullable()->constrained('connections')->nullOnDelete();
            $table->string('strategy', 24);
            $table->json('config');
            $table->unsignedSmallInteger('interval_minutes')->nullable();
            $table->timestamps();

            $table->unique('workflow_id', 'workflow_triggers_workflow_unique');
            $table->index(['strategy', 'interval_minutes'], 'workflow_triggers_strategy_interval_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_triggers');
    }
};
