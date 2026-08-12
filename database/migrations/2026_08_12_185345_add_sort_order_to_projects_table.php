<?php

use App\Models\Project;
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
        Schema::table('projects', function (Blueprint $table) {
            $table->unsignedInteger('sort_order')->default(0)->index()->after('id');
        });

        // Seeded from the order the dashboard already showed (newest first) so
        // nothing appears to jump the first time the site is loaded.
        Project::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get()
            ->each(fn (Project $project, int $index) => $project->updateQuietly(['sort_order' => $index + 1]));
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropIndex(['sort_order']);
            $table->dropColumn('sort_order');
        });
    }
};
