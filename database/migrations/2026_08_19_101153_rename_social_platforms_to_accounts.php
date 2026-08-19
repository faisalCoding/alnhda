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
        Schema::rename('social_platforms', 'accounts');
        Schema::rename('social_platform_tasks', 'account_tasks');

        Schema::table('account_tasks', function (Blueprint $table) {
            $table->renameColumn('social_platform_id', 'account_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('account_tasks', function (Blueprint $table) {
            $table->renameColumn('account_id', 'social_platform_id');
        });

        Schema::rename('account_tasks', 'social_platform_tasks');
        Schema::rename('accounts', 'social_platforms');
    }
};
