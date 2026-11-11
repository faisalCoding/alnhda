<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What one kind of property sold for in one place during one quarter, as
     * the authority published it.
     *
     * The authority has published these by district in some quarters and only
     * by city in others. Every file is therefore also kept at city level, so a
     * city has an unbroken history, and the district rows are kept where they
     * exist. The figures are the authority's own averages: there are no deeds
     * behind them here to take a median from.
     */
    public function up(): void
    {
        Schema::create('market_type_stats', function (Blueprint $table) {
            $table->id();
            $table->foreignId('market_indicator_file_id')->constrained()->cascadeOnDelete();
            $table->string('level', 10);
            $table->string('city_key', 80);
            $table->string('city', 80);
            $table->string('district_key', 120)->default('');
            $table->string('district', 120)->default('');
            $table->string('property_type', 30);
            $table->string('classification', 30)->default('');
            $table->unsignedInteger('deals_count')->default(0);
            $table->unsignedBigInteger('total_price')->default(0);
            $table->decimal('total_area', 18, 2)->default(0);
            $table->decimal('average_price_per_meter', 12, 2)->default(0);
            $table->decimal('lowest_price_per_meter', 12, 2)->nullable();
            $table->decimal('highest_price_per_meter', 12, 2)->nullable();
            $table->timestamps();

            $table->index(['level', 'city_key', 'market_indicator_file_id'], 'market_type_stats_city_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_type_stats');
    }
};
