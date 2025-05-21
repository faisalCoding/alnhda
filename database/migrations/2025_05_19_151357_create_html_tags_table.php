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
        Schema::create('html_tags', function (Blueprint $table) {
            $table->id();
            $table->string('tag_name');
            $table->text('classes')->nullable();
            $table->string('order_by')->default('0');
            $table->text('tag_attributes')->nullable();

            $table->unsignedBigInteger('blog_id')->nullable();
            $table->foreign('blog_id')
                ->references('id')->on('blogs')
                ->onDelete('cascade');

            $table->unsignedBigInteger('html_tag_id')->nullable();
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
        Schema::dropIfExists('html_tags');
    }
};
