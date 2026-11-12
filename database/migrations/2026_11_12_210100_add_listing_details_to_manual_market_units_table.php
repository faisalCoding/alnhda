<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A hand-entered unit as a property listing describes it.
     *
     * These units are mostly copied from listing sites — Aqar, Bayut, Haraj,
     * Property Finder — so they carry what a listing says: where it was
     * listed and under what number, its rooms, its street and facade, its
     * age, and when it was posted, last updated and seen. A name is no longer
     * required, since a listing's title is often all the name it has.
     *
     * One property is often listed on several sites, or twice on one. Each
     * listing keeps the others it was said to repeat, and the listings that
     * repeat one another share a group, so a comparison counts the property
     * once and not once per site.
     */
    public function up(): void
    {
        Schema::table('manual_market_units', function (Blueprint $table) {
            $table->string('name')->nullable()->change();
            $table->string('source_site', 40)->nullable()->after('project_name');
            $table->string('listing_id', 64)->nullable()->after('source_site');
            $table->unsignedSmallInteger('rooms')->nullable()->after('price_per_meter');
            $table->unsignedSmallInteger('bedrooms')->nullable()->after('rooms');
            $table->unsignedSmallInteger('bathrooms')->nullable()->after('bedrooms');
            $table->string('street_width', 40)->nullable()->after('bathrooms');
            $table->string('facade', 60)->nullable()->after('street_width');
            $table->string('property_age', 120)->nullable()->after('facade');
            $table->unsignedSmallInteger('property_age_years')->nullable()->after('property_age');
            $table->date('posted_on')->nullable()->after('property_age_years');
            $table->date('listing_updated_on')->nullable()->after('posted_on');
            $table->date('observed_on')->nullable()->after('listing_updated_on');
            $table->string('source_url', 2048)->nullable()->after('observed_on');
            $table->string('duplicates', 500)->nullable()->after('source_url');
            $table->string('duplicate_group', 120)->nullable()->after('duplicates');

            $table->unique(['source_site', 'listing_id'], 'manual_market_units_listing_unique');
            $table->index('duplicate_group', 'manual_market_units_duplicate_group_index');
        });
    }

    public function down(): void
    {
        Schema::table('manual_market_units', function (Blueprint $table) {
            $table->dropUnique('manual_market_units_listing_unique');
            $table->dropIndex('manual_market_units_duplicate_group_index');
            $table->dropColumn([
                'source_site', 'listing_id', 'rooms', 'bedrooms', 'bathrooms', 'street_width', 'facade',
                'property_age', 'property_age_years', 'posted_on', 'listing_updated_on', 'observed_on',
                'source_url', 'duplicates', 'duplicate_group',
            ]);
        });
    }
};
