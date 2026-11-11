<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Splits each place's figures by the size of what was sold, and keeps the
     * spread of prices beside the median.
     *
     * An empty area band is the row for every size together, the way an empty
     * classification is. A quarter remembers which version of the figures it
     * was counted with, because rows written before this change have no bands
     * and can only gain them by reading the file again. It also remembers
     * where its file is downloaded from and when the ministry last replaced it.
     */
    public function up(): void
    {
        Schema::table('market_price_stats', function (Blueprint $table) {
            $table->dropIndex('market_price_stats_level_index');
            $table->dropIndex('market_price_stats_city_index');
        });

        Schema::table('market_price_stats', function (Blueprint $table) {
            $table->string('area_band', 12)->default('')->after('classification');
            $table->decimal('lower_price_per_meter', 12, 2)->default(0)->after('median_price_per_meter');
            $table->decimal('upper_price_per_meter', 12, 2)->default(0)->after('lower_price_per_meter');
            $table->unsignedBigInteger('median_price')->default(0)->after('upper_price_per_meter');

            $table->index(['level', 'classification', 'area_band', 'market_quarter_id'], 'market_price_stats_level_index');
            $table->index(['level', 'city_key', 'classification', 'area_band'], 'market_price_stats_city_index');
        });

        Schema::table('market_quarters', function (Blueprint $table) {
            $table->unsignedTinyInteger('figures_version')->default(1)->after('skipped_rows');
            $table->string('download_url', 600)->nullable()->after('dataset_id');
            $table->timestamp('file_updated_at')->nullable()->after('download_url');
        });
    }

    public function down(): void
    {
        Schema::table('market_quarters', function (Blueprint $table) {
            $table->dropColumn(['figures_version', 'download_url', 'file_updated_at']);
        });

        Schema::table('market_price_stats', function (Blueprint $table) {
            $table->dropIndex('market_price_stats_level_index');
            $table->dropIndex('market_price_stats_city_index');
        });

        Schema::table('market_price_stats', function (Blueprint $table) {
            $table->dropColumn(['area_band', 'lower_price_per_meter', 'upper_price_per_meter', 'median_price']);

            $table->index(['level', 'classification', 'market_quarter_id'], 'market_price_stats_level_index');
            $table->index(['level', 'city_key', 'classification'], 'market_price_stats_city_index');
        });
    }
};
