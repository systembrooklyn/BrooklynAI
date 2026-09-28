<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('workflow_triggers', function (Blueprint $table) {
            // Generic due-query index for the production scheduler. The
            // existing (integration_key, trigger_key, next_poll_at) composite
            // does not serve the generic query because next_poll_at is not the
            // leading column.
            $table->index('next_poll_at', 'workflow_triggers_next_poll_at_index');
        });
    }

    public function down(): void
    {
        Schema::table('workflow_triggers', function (Blueprint $table) {
            $table->dropIndex('workflow_triggers_next_poll_at_index');
        });
    }
};
