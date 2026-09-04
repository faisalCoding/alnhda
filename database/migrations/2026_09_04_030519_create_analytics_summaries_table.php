<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The breakdowns that only make sense over a period rather than a day:
     * which pages were read, where the visits came from, on what device, from
     * which city. Replaced wholesale on each pull.
     */
    public function up(): void
    {
        Schema::create('analytics_summaries', function (Blueprint $table) {
            $table->id();
            $table->string('period')->unique();
            $table->timestamp('pulled_at')->nullable();
            $table->json('top_pages')->nullable();
            $table->json('channels')->nullable();
            $table->json('devices')->nullable();
            $table->json('cities')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_summaries');
    }
};
