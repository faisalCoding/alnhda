<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What every page falls back to when nothing more specific is set. The
     * defaults themselves lived in the layout as literal strings, which put
     * them out of reach of anyone without a deploy.
     */
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->string('seo_default_title')->nullable();
            $table->text('seo_default_description')->nullable();
            $table->string('seo_default_image_path')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn(['seo_default_title', 'seo_default_description', 'seo_default_image_path']);
        });
    }
};
