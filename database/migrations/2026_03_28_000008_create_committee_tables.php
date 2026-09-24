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
        Schema::create('event_owner_preferences', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eventOwnerId')->unique()->constrained('eventOwners')->cascadeOnDelete();
            $table->string('eventLanguage', 8)->default('sw');
            $table->string('timezone', 80)->default('Africa/Dar_es_Salaam');
            $table->jsonb('preferredChannels')->default('{"sms": true, "whatsapp": false}');
            $table->jsonb('committeeSettings')->default('{}');
            $table->jsonb('messagePreferences')->default('{}');
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent();
        });

        Schema::create('event_committee_members', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eventId')->constrained('events')->cascadeOnDelete();
            $table->string('fullName', 220);
            $table->string('normalPhone', 40)->nullable();
            $table->string('whatsappPhone', 40)->nullable();
            $table->string('title', 80)->nullable();
            $table->string('role', 80)->nullable();
            $table->string('customRole', 160)->nullable();
            $table->string('status', 32)->default('active'); // active, inactive, removed
            $table->timestamp('welcomeSentAt')->nullable();
            $table->timestamp('lastReminderSentAt')->nullable();
            $table->timestamp('autoEventReminderLastSentAt')->nullable();
            $table->jsonb('metadata')->default('{}');
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent();

            $table->index('eventId');
            $table->index('status');
        });

        Schema::create('committee_meetings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eventId')->constrained('events')->cascadeOnDelete();
            $table->string('title', 220)->nullable();
            $table->date('meetingDate');
            $table->time('meetingTime')->nullable();
            $table->string('location', 220)->nullable();
            $table->integer('intervalCount')->nullable();
            $table->string('intervalUnit', 20)->nullable(); // days, weeks, months
            $table->date('nextMeetingDate')->nullable();
            $table->string('status', 32)->default('scheduled'); // scheduled, completed, cancelled
            $table->string('reminderStatus', 32)->default('pending'); // pending, sent, failed, skipped
            $table->timestamp('lastReminderSentAt')->nullable();
            $table->jsonb('metadata')->default('{}');
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent();

            $table->index('eventId');
            $table->index(['nextMeetingDate', 'status']);
        });

        Schema::create('committee_meeting_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('meetingId')->constrained('committee_meetings')->cascadeOnDelete();
            $table->foreignId('assignedMemberId')->nullable()->constrained('event_committee_members')->nullOnDelete();
            $table->foreignId('createdByUserId')->nullable()->constrained('users')->nullOnDelete();
            $table->text('content');
            $table->string('type', 32)->default('note'); // note, todo, decision
            $table->string('status', 32)->default('open'); // open, done, cancelled
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent();

            $table->index('meetingId');
            $table->index('assignedMemberId');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('committee_meeting_notes');
        Schema::dropIfExists('committee_meetings');
        Schema::dropIfExists('event_committee_members');
        Schema::dropIfExists('event_owner_preferences');
    }
};
