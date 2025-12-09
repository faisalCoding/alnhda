<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

use function Laravel\Prompts\table;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('properties', function (Blueprint $table) {
            $table->id();
            $table->text('name');
            $table->unsignedBigInteger('project_id');
            $table->foreign('project_id')->references('id')->on('projects');
            $table->integer('price')->nullable();
            $table->integer('offer')->nullable();
            $table->text('status')->default('جديد');
            $table->text('properties_image')->nullable();
            $table->integer('rooms')->nullable();
            $table->integer('bathrooms')->nullable();
            $table->integer('living_rooms')->nullable();
            $table->boolean('mainds_room')->default(false);
            $table->integer('area')->nullable();
            $table->integer('doors')->default(1);
            $table->text('type')->default('وحدة عقارية');
            $table->integer('parkings')->nullable();
            $table->integer('driver_room')->nullable();
            $table->text('facade')->nullable();
            $table->boolean('furniture')->default(false);
            $table->text('unit_youtube')->nullable();
            $table->text('stages_building_youtube')->nullable();



            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('properties');
    }
};
