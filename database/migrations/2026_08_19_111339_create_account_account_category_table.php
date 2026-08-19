<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('account_account_category', function (Blueprint $table) {
            $table->id();
            $table->foreignId('account_id')->constrained()->cascadeOnDelete();
            $table->foreignId('account_category_id')->constrained()->cascadeOnDelete();
            $table->unique(['account_id', 'account_category_id']);
        });

        // Carry the single category each account already has into the pivot.
        DB::table('accounts')
            ->whereNotNull('account_category_id')
            ->orderBy('id')
            ->each(function (object $account): void {
                DB::table('account_account_category')->insert([
                    'account_id' => $account->id,
                    'account_category_id' => $account->account_category_id,
                ]);
            });

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropConstrainedForeignId('account_category_id');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->foreignId('account_category_id')->nullable()->constrained()->nullOnDelete();
        });

        // Only the first category of each account can survive the trip back.
        DB::table('account_account_category')->orderBy('id')->each(function (object $row): void {
            DB::table('accounts')
                ->where('id', $row->account_id)
                ->whereNull('account_category_id')
                ->update(['account_category_id' => $row->account_category_id]);
        });

        Schema::dropIfExists('account_account_category');
    }
};
