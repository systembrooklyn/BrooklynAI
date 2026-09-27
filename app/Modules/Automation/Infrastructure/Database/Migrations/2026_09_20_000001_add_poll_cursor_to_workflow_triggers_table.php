<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_triggers', function (Blueprint $table) {
            $table->bigInteger('poll_cursor')
                ->nullable()
                ->after('interval_minutes');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_triggers', function (Blueprint $table) {
            $table->dropColumn('poll_cursor');
        });
    }
};
