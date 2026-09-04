<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * One row per day of what Google Analytics already recorded.
     *
     * The site never counts its own visitors: the browser reports to Google and
     * a nightly job copies the totals here, so a page view costs this server
     * nothing at all. A year of traffic is 365 rows.
     */
    public function up(): void
    {
        Schema::create('analytics_days', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->unsignedInteger('users')->default(0);
            $table->unsignedInteger('sessions')->default(0);
            $table->unsignedInteger('views')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('analytics_days');
    }
};
