<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The plot a unit stands on, apart from its own floor area.
     *
     * The market prices a villa, a duplex or a plot by its land: a deed, the
     * authority's averages and a listing all give a villa's area as the plot's.
     * A unit's own area is what a buyer is shown and is often what was built.
     * Compared by that, a villa looks cheaper by the metre than it is; compared
     * by its land, it reads like the market it is set beside.
     */
    public function up(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->decimal('land_area', 12, 2)->nullable()->after('area');
        });
    }

    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn('land_area');
        });
    }
};
