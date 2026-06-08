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
        Schema::table('properties', function (Blueprint $table) {
            $table->string('pdf_path')->nullable();
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->string('map_url')->nullable();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('properties', function (Blueprint $table) {
            $table->dropColumn('pdf_path');
        });

        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('map_url');
        });
    }
};
