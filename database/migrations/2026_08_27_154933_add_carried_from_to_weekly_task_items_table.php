<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * The week an unfinished task was carried forward from. Kept on the item
     * rather than inferred, so a task that has been outstanding for three weeks
     * still names the week it was first owed in.
     */
    public function up(): void
    {
        Schema::table('weekly_task_items', function (Blueprint $table) {
            $table->date('carried_from')->nullable()->after('sort_order');
        });
    }

    public function down(): void
    {
        Schema::table('weekly_task_items', function (Blueprint $table) {
            $table->dropColumn('carried_from');
        });
    }
};
