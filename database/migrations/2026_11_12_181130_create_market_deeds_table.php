<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The deeds themselves, for the cities the company works in.
     *
     * Everywhere else a quarter is kept only as what it added up to. Here each
     * sale is kept as well, so a district can be read deed by deed, narrowed to
     * any range of area or price, and followed month by month. That is a few
     * thousand rows a quarter for one city; for the whole country it would be
     * eighty thousand, which is why it is not done for all of it.
     */
    public function up(): void
    {
        Schema::create('market_deeds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('market_quarter_id')->constrained()->cascadeOnDelete();
            $table->string('city_key', 80);
            $table->string('city', 80);
            $table->string('district_key', 120)->default('');
            $table->string('district', 120)->default('');
            $table->string('classification', 30)->default('');
            $table->date('deed_date');
            $table->unsignedBigInteger('price');
            $table->decimal('area', 14, 2);
            $table->decimal('price_per_meter', 12, 2);
            $table->unsignedInteger('properties_count')->default(1);

            $table->index(['city_key', 'deed_date'], 'market_deeds_city_index');
            $table->index(['city_key', 'district_key', 'deed_date'], 'market_deeds_district_index');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_deeds');
    }
};
