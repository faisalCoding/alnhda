<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The icon shown in the browser tab and beside the site in search results.
     * Two files rather than one: Google wants a square whose side is a multiple
     * of 48, and iOS wants 180 for a home-screen shortcut.
     */
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->string('favicon_path')->nullable();
            $table->string('apple_touch_icon_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn(['favicon_path', 'apple_touch_icon_path']);
        });
    }
};
