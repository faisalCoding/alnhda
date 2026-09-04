<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * What the web server itself saw, which analytics cannot see: crawlers,
     * visitors who block tracking, requests that ended in an error, and the
     * bandwidth all of it cost.
     *
     * Read from a log Apache writes anyway, once a night, so nothing is added
     * to the cost of serving a request.
     */
    public function up(): void
    {
        Schema::create('server_log_days', function (Blueprint $table) {
            $table->id();
            $table->date('date')->unique();
            $table->unsignedInteger('requests')->default(0);
            $table->unsignedInteger('unique_addresses')->default(0);
            $table->unsignedBigInteger('bytes')->default(0);
            $table->unsignedInteger('bot_requests')->default(0);
            $table->unsignedInteger('status_2xx')->default(0);
            $table->unsignedInteger('status_3xx')->default(0);
            $table->unsignedInteger('status_4xx')->default(0);
            $table->unsignedInteger('status_5xx')->default(0);
            $table->json('top_paths')->nullable();
            $table->json('top_bots')->nullable();
            $table->json('not_found')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('server_log_days');
    }
};
