<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The market units chosen by hand as a unit's competitors.
     *
     * The units report finds comparables by rule — same kind, same district,
     * about the same size. These are the ones a person knows a buyer will set
     * beside the unit: the villa down the street, the rival project's flat.
     * Either side going takes the link with it.
     */
    public function up(): void
    {
        Schema::create('property_competitors', function (Blueprint $table) {
            $table->id();
            $table->foreignId('properties_id')->constrained('properties')->cascadeOnDelete();
            $table->foreignId('manual_market_unit_id')->constrained()->cascadeOnDelete();
            $table->string('note', 500)->nullable();
            $table->timestamps();

            $table->unique(['properties_id', 'manual_market_unit_id'], 'property_competitors_pair_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('property_competitors');
    }
};
