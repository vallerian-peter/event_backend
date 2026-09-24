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
        Schema::create('newsletter_subscribers', function (Blueprint $table) {
            $table->id();
            $table->string('email', 191)->unique();
            $table->string('source', 50)->default('footer');
            $table->string('status', 20)->default('active'); // active, unsubscribed
            $table->string('ipAddress', 45)->nullable();
            $table->text('userAgent')->nullable();
            $table->timestamp('verifiedAt')->nullable();
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent();

            $table->index('status');
        });

        Schema::create('project_briefs', function (Blueprint $table) {
            $table->id();
            $table->string('referenceCode', 30)->unique();
            $table->string('fullName', 150);
            $table->string('email', 191);
            $table->string('phone', 50);
            $table->string('eventType', 80);
            $table->date('eventDate')->nullable();
            $table->string('eventTime', 20)->nullable();
            $table->string('venueLocation', 150)->nullable();
            $table->string('estimatedGuests', 50)->nullable();
            $table->string('budgetRange', 50)->nullable();
            $table->json('requestedServices')->nullable();
            $table->text('message');
            $table->string('status', 30)->default('new'); // new, contacted, in_discussion, converted, closed
            $table->text('adminNotes')->nullable();
            $table->foreignId('assignedToUserId')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('contactedAt')->nullable();
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent();

            $table->index(['status', 'createdAt']);
            $table->index('email');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('project_briefs');
        Schema::dropIfExists('newsletter_subscribers');
    }
};
