<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A page an admin composes out of records that already exist: a campaign
     * landing page gathering the projects, units and articles that belong
     * together, without any of them being copied.
     *
     * The address is a written slug rather than an id because this link is
     * pasted into campaigns and read before it is opened.
     */
    public function up(): void
    {
        Schema::create('collection_pages', function (Blueprint $table) {
            $table->id();
            $table->string('slug')->unique();
            $table->string('title');
            $table->text('description')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('collection_pages');
    }
};
