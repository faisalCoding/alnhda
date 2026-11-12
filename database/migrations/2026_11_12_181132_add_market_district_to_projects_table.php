<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Where a project stands in the market's own terms.
     *
     * A project's location is written for a buyer to read — «جدة مخطط
     * التيسير» — and matches nothing in the ministry's files. These name the
     * city and district the deeds use, so a project's prices can be set beside
     * what was actually paid around it.
     */
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('market_city_key', 80)->nullable();
            $table->string('market_district_key', 120)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['market_city_key', 'market_district_key']);
        });
    }
};
