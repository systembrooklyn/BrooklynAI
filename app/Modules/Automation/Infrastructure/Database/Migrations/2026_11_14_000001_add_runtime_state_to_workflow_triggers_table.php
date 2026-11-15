<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_triggers', function (Blueprint $table) {
            $table->json('runtime_state')->nullable()->after('poll_cursor');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_triggers', function (Blueprint $table) {
            $table->dropColumn('runtime_state');
        });
    }
};
