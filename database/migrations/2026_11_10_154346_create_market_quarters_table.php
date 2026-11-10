<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per quarter of sale deeds the Ministry of Justice has published.
     *
     * A quarter exists here from the moment the open data portal lists it, and
     * is marked imported once its file has been read: the gap between the two
     * is what the panel shows as waiting to be uploaded.
     */
    public function up(): void
    {
        Schema::create('market_quarters', function (Blueprint $table) {
            $table->id();
            $table->unsignedSmallInteger('year');
            $table->unsignedTinyInteger('quarter');
            $table->string('dataset_id', 64)->nullable();
            $table->string('source_name')->nullable();
            $table->unsignedInteger('deals_count')->default(0);
            $table->unsignedInteger('skipped_rows')->default(0);
            $table->timestamp('imported_at')->nullable();
            $table->timestamps();

            $table->unique(['year', 'quarter']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('market_quarters');
    }
};
