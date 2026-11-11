<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per quarterly file of sale indicators the Real Estate General
     * Authority publishes for a region.
     *
     * These are the only published figures that name the kind of property —
     * flat, villa, floor, land — which the Ministry of Justice deeds do not.
     * Like a quarter of deeds, a file exists here once the portal lists it and
     * is marked imported once it has been uploaded.
     */
    public function up(): void
    {
        Schema::create('market_indicator_files', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('quarter');
            $table->string('region_key', 60);
            $table->string('region', 60);
            $table->string('dataset_id', 64)->nullable();
            $table->string('download_url', 600)->nullable();
            $table->timestamp('file_updated_at')->nullable();
            $table->string('source_name')->nullable();
            $table->unsignedInteger('deals_count')->default(0);
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->unique(['year', 'quarter', 'region_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_indicator_files');
    }
};
