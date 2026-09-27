<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('workflows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->string('status', 32)->default('draft');
            $table->softDeletes();
            $table->timestamps();

            $table->index(['user_id', 'status'], 'workflows_user_status_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('workflows');
    }
};
