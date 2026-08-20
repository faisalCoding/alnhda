<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('weekly_task_categories', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('color')->default('zinc');
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::table('weekly_task_templates', function (Blueprint $table) {
            $table->foreignId('weekly_task_category_id')->nullable()->after('employee_id')->constrained()->nullOnDelete();
        });

        // Carried onto the item as well as the template, so a list keeps the
        // grouping it was generated with even after the template moves on.
        Schema::table('weekly_task_items', function (Blueprint $table) {
            $table->foreignId('weekly_task_category_id')->nullable()->after('weekly_task_list_id')->constrained()->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('weekly_task_items', function (Blueprint $table) {
            $table->dropConstrainedForeignId('weekly_task_category_id');
        });

        Schema::table('weekly_task_templates', function (Blueprint $table) {
            $table->dropConstrainedForeignId('weekly_task_category_id');
        });

        Schema::dropIfExists('weekly_task_categories');
    }
};
