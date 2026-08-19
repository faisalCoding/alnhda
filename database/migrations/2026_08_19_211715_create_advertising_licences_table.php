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
        Schema::create('advertising_licences', function (Blueprint $table) {
            $table->id();
            // Either points at a unit on file, or names one typed by hand.
            $table->foreignId('properties_id')->nullable()->constrained()->nullOnDelete();
            $table->string('unit_name')->nullable();
            $table->string('licence_number');
            $table->date('expires_on')->nullable();
            $table->text('note')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('advertising_licences');
    }
};
