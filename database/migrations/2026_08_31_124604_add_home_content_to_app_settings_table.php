<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The words on the front of the site, and the guarantees it lists.
     *
     * Every column is nullable and every one means the same thing when empty:
     * "keep what the site already says". Nothing here replaces the built-in
     * text until somebody types over it on purpose.
     */
    public function up(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->string('hero_eyebrow')->nullable();
            $table->string('hero_title')->nullable();
            $table->text('hero_subtitle')->nullable();
            $table->string('hero_primary_label')->nullable();
            $table->string('hero_secondary_label')->nullable();
            $table->json('home_guarantees')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('app_settings', function (Blueprint $table) {
            $table->dropColumn([
                'hero_eyebrow',
                'hero_title',
                'hero_subtitle',
                'hero_primary_label',
                'hero_secondary_label',
                'home_guarantees',
            ]);
        });
    }
};
