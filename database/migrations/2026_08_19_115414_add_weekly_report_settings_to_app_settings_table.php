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
        Schema::table('app_settings', function (Blueprint $table) {
            $table->string('whatsapp_group_id')->nullable();
            $table->string('whatsapp_group_name')->nullable();
            $table->boolean('weekly_reports_enabled')->default(false);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn(['whatsapp_group_id', 'whatsapp_group_name', 'weekly_reports_enabled']);
        });
    }
};
