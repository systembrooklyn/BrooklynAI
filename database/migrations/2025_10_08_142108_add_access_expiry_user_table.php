<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {


            $table->date('access_expiry')->default(now()->addMonth())->after('has_bot_access');
        });
    }


    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {


            $table->dropColumn([
                'access_expiry',

            ]);
        });
    }
};
