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
        Schema::create('whatsapp_message_recipients', function (Blueprint $table) {
            $table->id();
            $table->foreignId('whatsapp_message_id')->constrained()->cascadeOnDelete();

            // The lead may be deleted later; the name and phone are snapshotted
            // so the history still says who was actually messaged.
            $table->foreignId('lead_id')->nullable()->constrained()->nullOnDelete();
            $table->string('name');
            $table->string('phone');

            $table->string('status')->default('queued');
            $table->string('provider_message_id')->nullable();
            $table->text('error')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('read_at')->nullable();
            $table->timestamps();

            $table->index(['whatsapp_message_id', 'status']);
            $table->index('provider_message_id');
            $table->index('status');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_message_recipients');
    }
};
