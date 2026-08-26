<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table): void {
            $table->string('seo_keywords')->nullable()->after('seo_default_description');
            $table->string('seo_author')->nullable()->after('seo_keywords');
            $table->string('seo_theme_color', 7)->nullable()->after('seo_author');
        });

        Schema::table('seo_meta', function (Blueprint $table): void {
            $table->string('keywords')->nullable()->after('description');
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table): void {
            $table->dropColumn(['seo_keywords', 'seo_author', 'seo_theme_color']);
        });

        Schema::table('seo_meta', function (Blueprint $table): void {
            $table->dropColumn('keywords');
        });
    }
};
