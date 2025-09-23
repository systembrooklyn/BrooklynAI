<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->softDeletes();
            
            $table->integer('st_num')->nullable()->after('name');
            $table->string('google_id')->nullable()->unique()->after('st_num');
            $table->string('avatar')->nullable()->after('google_id');
        });
    }


    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropSoftDeletes();
            
            $table->dropColumn([
                'st_num',
                'google_id',
                'avatar',
            ]);
        });
    }
};