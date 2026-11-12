<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What kind of property a unit is, in the words the Real Estate General
     * Authority prices by: شقة, فيلا, دور, دوبلكس, أرض.
     *
     * A unit's own type is written for a buyer and is often the default
     * «وحدة عقارية». This names the kind the market figures use, so a flat is
     * read against what flats sold for and a villa against villas. Empty
     * means it has not been set by hand and is guessed from the unit and its
     * project.
     */
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->string('market_type', 30)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn('market_type');
        });
    }
};
