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
        Schema::create('image_properties', function (Blueprint $table) {
            $table->id();
            $table->text('url');
            $table->integer('order_by')->default(1);
            $table->unsignedBigInteger('properties_id');
            $table->foreign('properties_id')
                ->references('id')->on('properties')
                ->onDelete('cascade');
            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('image_properties');
    }
};
