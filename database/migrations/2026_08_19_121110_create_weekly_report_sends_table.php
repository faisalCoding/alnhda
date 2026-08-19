<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('weekly_report_sends', function (Blueprint $table) {
            $table->id();
            $table->date('week_start');
            $table->string('kind');
            $table->timestamp('sent_at');
            $table->timestamps();

            // One report of each kind per week, whatever calls it how often.
            $table->unique(['week_start', 'kind']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('weekly_report_sends');
    }
};
