<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('executions', function (Blueprint $table) {
            $table->unsignedTinyInteger('retry_attempts')->default(0)->after('error_message');
            $table->foreignId('retry_of_id')
                ->nullable()
                ->after('retry_attempts')
                ->constrained('executions')
                ->nullOnDelete();

            $table->index(
                ['trigger_source', 'status', 'retry_attempts', 'updated_at'],
                'executions_recovery_scan_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('executions', function (Blueprint $table) {
            $table->dropForeign(['retry_of_id']);
            $table->dropIndex('executions_recovery_scan_index');
            $table->dropColumn(['retry_attempts', 'retry_of_id']);
        });
    }
};
