<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflow_steps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('workflow_id')->constrained('workflows')->cascadeOnDelete();
            $table->unsignedSmallInteger('position');
            $table->string('integration_key', 64);
            $table->string('action_key', 64);
            $table->foreignId('connection_id')->nullable()->constrained('connections')->nullOnDelete();
            $table->json('config');
            $table->timestamps();

            $table->unique(['workflow_id', 'position'], 'workflow_steps_workflow_position_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflow_steps');
    }
};
