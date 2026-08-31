<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The picture behind the front of the site, and the sections the site is
     * allowed to skip.
     *
     * Hidden sections are stored as a list of what is switched off rather than
     * what is on, so a section added later is visible without anybody having to
     * go and enable it.
     */
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->string('hero_image_path')->nullable();
            $table->json('hidden_home_sections')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn(['hero_image_path', 'hidden_home_sections']);
        });
    }
};
