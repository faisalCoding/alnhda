<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What a quarter's deeds add up to for one place and one classification.
     *
     * The deeds themselves are never stored: a quarter is some eighty thousand
     * rows, and the panel only ever asks what a district, a city, a region or
     * the country traded at. The median is worked out while the file is read
     * because it cannot be rebuilt from totals afterwards.
     *
     * Each place carries a spelling to show and a key to match on, since the
     * ministry writes the same district several ways from one quarter to the
     * next. An empty classification is the row for every classification taken
     * together.
     */
    public function up(): void
    {
        Schema::create('market_price_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('market_quarter_id')->constrained()->cascadeOnDelete();
            $table->string('level', 10);
            $table->string('region_key', 60)->default('');
            $table->string('region', 60)->default('');
            $table->string('city_key', 80)->default('');
            $table->string('city', 80)->default('');
            $table->string('district_key', 120)->default('');
            $table->string('district', 120)->default('');
            $table->string('classification', 30)->default('');
            $table->unsignedInteger('deals_count')->default(0);
            $table->unsignedInteger('properties_count')->default(0);
            $table->unsignedBigInteger('total_price')->default(0);
            $table->decimal('total_area', 18, 2)->default(0);
            $table->decimal('median_price_per_meter', 12, 2)->default(0);
            $table->timestamps();

            $table->index(['level', 'classification', 'market_quarter_id'], 'market_price_stats_level_index');
            $table->index(['level', 'city_key', 'classification'], 'market_price_stats_city_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_price_stats');
    }
};
