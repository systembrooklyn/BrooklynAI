<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_triggers', function (Blueprint $table) {
            $table->timestamp('next_poll_at')->nullable()->after('poll_cursor');

            $table->index(
                ['integration_key', 'trigger_key', 'next_poll_at'],
                'workflow_triggers_poll_due_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('workflow_triggers', function (Blueprint $table) {
            $table->dropIndex('workflow_triggers_poll_due_index');
            $table->dropColumn('next_poll_at');
        });
    }
};
