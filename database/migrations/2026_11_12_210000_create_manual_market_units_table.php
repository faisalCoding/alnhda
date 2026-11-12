<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Units an admin enters by hand, to set the company's prices beside a
     * market the ministry's deeds do not describe.
     */
    public function up(): void
    {
        Schema::create('manual_market_units', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('project_name')->nullable();
            $table->string('city', 80);
            $table->string('city_key', 80);
            $table->string('district', 120);
            $table->string('district_key', 120);
            $table->string('kind', 30);
            $table->decimal('area', 14, 2);
            $table->unsignedBigInteger('price');
            $table->decimal('price_per_meter', 12, 2);
            $table->timestamps();

            $table->index(['city_key', 'district_key', 'kind'], 'manual_market_units_place_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('manual_market_units');
    }
};
