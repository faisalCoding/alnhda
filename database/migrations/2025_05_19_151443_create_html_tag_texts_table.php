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
        Schema::create('html_tag_texts', function (Blueprint $table) {
            $table->id();
            $table->text('content')->default("");

            $table->unsignedBigInteger('html_tag_id');
            $table->foreign('html_tag_id')
                ->references('id')->on('html_tags')
                ->onDelete('cascade');

            $table->timestamps();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('html_tag_texts');
    }
};
