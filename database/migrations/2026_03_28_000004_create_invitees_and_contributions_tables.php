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
        Schema::create('invitees', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eventId')->constrained('events')->cascadeOnDelete();
            $table->string('fullName');
            $table->string('title', 220)->default('Normal Person');
            $table->string('email')->nullable();
            $table->string('normalPhone')->nullable();
            $table->string('whatsappPhone')->nullable();
            $table->string('status')->default('active'); // active, pending, confirmed, declined, attended
            $table->string('smsStatus')->default('pending'); // pending, sent, delivered, failed
            $table->string('division', 16)->nullable(); // single, double, both
            $table->string('contributionStatus')->default('pending'); // pending, paid, failed
            $table->string('entryPassCode', 16)->nullable();
            $table->string('entryPassTokenHash', 64)->nullable()->index();
            $table->timestamp('entryPassConsumedAt')->nullable();
            $table->string('entryPassScannedByUuid', 64)->nullable();
            $table->timestamp('autoReminderLastSentAt')->nullable();
            $table->timestamp('autoEntryLastSentAt')->nullable();
            $table->timestamp('autoParticipationThanksSentAt')->nullable();
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent();

            $table->index('eventId');
            $table->index('status');
            $table->index('smsStatus');
            $table->index('contributionStatus');
        });

        Schema::create('contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('inviteeId')->nullable()->constrained('invitees')->cascadeOnDelete();
            $table->foreignId('eventId')->nullable()->constrained('events')->cascadeOnDelete();
            $table->string('pledgeAmount')->default('0');
            $table->string('paidAmount')->default('0');
            $table->string('division', 16)->nullable();
            $table->string('status')->default('pending'); // pending, paid, failed
            $table->string('transactionId')->nullable()->index();
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent();

            $table->index(['eventId', 'status']);
            $table->index('inviteeId');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('contributions');
        Schema::dropIfExists('invitees');
    }
};
