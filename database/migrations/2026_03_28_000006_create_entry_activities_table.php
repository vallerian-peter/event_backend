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
        Schema::create('entry_activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eventId')->constrained('events')->cascadeOnDelete();
            $table->string('entryType'); // 'invitee' | 'ticket'
            $table->foreignId('inviteeId')->nullable()->constrained('invitees')->cascadeOnDelete();
            $table->foreignId('ticketId')->nullable()->constrained('tickets')->cascadeOnDelete();
            $table->foreignId('gateKeeperId')->nullable()->constrained('gateKeepers')->nullOnDelete();
            $table->string('scannedCode');
            $table->string('status'); // success, duplicate, invalid, expired, denied
            $table->string('failureReason')->nullable();
            $table->string('gateName')->nullable();
            $table->jsonb('deviceInfo')->nullable();
            $table->boolean('isOfflineSync')->default(false);
            $table->timestamp('scannedAt');
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent();

            $table->index(['eventId', 'status']);
            $table->index(['eventId', 'entryType']);
            $table->index('scannedCode');
            $table->index('scannedAt');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('entry_activities');
    }
};
