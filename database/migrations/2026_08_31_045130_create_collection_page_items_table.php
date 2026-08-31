<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What a collection page shows, in the order it was arranged.
     *
     * A record appears once per page — the unique index says so rather than
     * leaving it to the screen to remember.
     */
    public function up(): void
    {
        Schema::create('collection_page_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('collection_page_id')->constrained()->cascadeOnDelete();
            $table->morphs('item');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['collection_page_id', 'item_type', 'item_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_page_items');
    }
};
