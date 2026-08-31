<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A button at the end of an article, pointing at another record on the site.
     *
     * The destination is stored as a record, not as a typed-in address, so the
     * link keeps working when a route changes and resolves to nothing — rather
     * than to a 404 — once the record it points at is deleted.
     */
    public function up(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->string('cta_label')->nullable()->after('image_post');
            $table->nullableMorphs('cta_target');
        });
    }

    public function down(): void
    {
        Schema::table('articles', function (Blueprint $table) {
            $table->dropMorphs('cta_target');
            $table->dropColumn('cta_label');
        });
    }
};
