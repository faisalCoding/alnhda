<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Who placed a listing: an owner, a brokerage, or the company itself. The
     * company's own listings are its own units on the market, and a unit set
     * beside them would be set beside itself.
     */
    public function up(): void
    {
        Schema::table('manual_market_units', function (Blueprint $table) {
            $table->string('advertiser')->nullable()->after('listing_id');
        });
    }

    public function down(): void
    {
        Schema::table('manual_market_units', function (Blueprint $table) {
            $table->dropColumn('advertiser');
        });
    }
};
