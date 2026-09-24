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
        Schema::create('eventsPayments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eventOwnerId')->constrained('eventOwners')->cascadeOnDelete();
            $table->foreignId('systemUserId')->nullable()->constrained('systemUsers')->nullOnDelete();
            $table->foreignId('eventId')->nullable()->constrained('events')->cascadeOnDelete();
            $table->jsonb('eventData');
            $table->string('amount');
            $table->string('paidAmount')->default('0');
            $table->string('currency')->default('TZS');
            $table->string('paymentMethod')->default('mobile');
            $table->string('mobileNumber')->nullable();
            $table->jsonb('bankDetails')->nullable();
            $table->string('transactionId')->nullable()->index();
            $table->string('status')->default('pending'); // pending, paid, failed
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent();

            $table->index('eventOwnerId');
            $table->index('status');
        });

        Schema::create('eventsTemplatePayment', function (Blueprint $table) {
            $table->id();
            $table->foreignId('templateId')->constrained('templates')->cascadeOnDelete();
            $table->foreignId('eventId')->constrained('events')->cascadeOnDelete();
            $table->string('paymentStatus', 20)->default('not-payed');
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent();

            $table->index('eventId');
            $table->index('templateId');
        });

        Schema::create('service_packages', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->integer('version')->default(1);
            $table->string('name', 120);
            $table->text('description')->nullable();
            $table->decimal('priceUsd', 10, 2)->default(0.00);
            $table->string('pricingModel', 32)->default('fixed'); // fixed, per_invitee
            $table->decimal('pricePerInviteeTzs', 14, 2)->nullable();
            $table->jsonb('capabilities')->default('{}');
            $table->jsonb('limits')->default('{}');
            $table->boolean('isActive')->default(true);
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent();

            $table->index('isActive');
        });

        Schema::create('event_package_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eventId')->constrained('events')->cascadeOnDelete();
            $table->foreignId('servicePackageId')->constrained('service_packages')->cascadeOnDelete();
            $table->unsignedBigInteger('paymentId')->nullable();
            $table->string('purchaseType', 32)->default('initial'); // initial, upgrade, addon
            $table->string('status', 32)->default('active'); // active, expired, cancelled
            $table->jsonb('packageSnapshot');
            $table->decimal('recordedAmountTzs', 14, 2)->default(0.00);
            $table->integer('inviteeLimit')->default(0);
            $table->timestamp('activatedAt')->useCurrent();
            $table->timestamp('expiresAt')->nullable();
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent();

            $table->index('eventId');
            $table->index('servicePackageId');
            $table->index('status');
        });

        Schema::create('event_feature_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eventId')->constrained('events')->cascadeOnDelete();
            $table->foreignId('subscriptionId')->nullable()->constrained('event_package_subscriptions')->cascadeOnDelete();
            $table->string('featureKey', 80);
            $table->integer('grantedUnits')->default(0);
            $table->integer('reservedUnits')->default(0);
            $table->integer('consumedUnits')->default(0);
            $table->jsonb('metadata')->default('{}');
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent();

            $table->unique(['eventId', 'featureKey']);
            $table->index('subscriptionId');
        });

        Schema::create('message_records', function (Blueprint $table) {
            $table->id();
            $table->string('batchId', 120)->nullable();
            $table->foreignId('eventId')->constrained('events')->cascadeOnDelete();
            $table->foreignId('inviteeId')->nullable()->constrained('invitees')->cascadeOnDelete();
            $table->string('featureKey', 80);
            $table->string('messagePurpose', 80);
            $table->string('channel', 40); // sms, whatsapp, email
            $table->integer('units')->default(1);
            $table->string('status', 32)->default('reserved'); // reserved, pending, sent, delivered, failed
            $table->string('provider', 64)->nullable();
            $table->string('providerReference', 191)->nullable();
            $table->string('idempotencyKey', 191)->unique();
            $table->text('errorMessage')->nullable();
            $table->jsonb('payload')->default('{}');
            $table->timestamp('reservedAt')->useCurrent();
            $table->timestamp('sentAt')->nullable();
            $table->timestamp('deliveredAt')->nullable();
            $table->timestamp('failedAt')->nullable();
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent();

            $table->index(['eventId', 'status']);
            $table->index('inviteeId');
            $table->index('providerReference');
            $table->index('batchId');
        });

        Schema::create('messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eventId')->nullable()->constrained('events')->cascadeOnDelete();
            $table->jsonb('invitees')->nullable();
            $table->string('status')->nullable();
            $table->text('message')->nullable();
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent();

            $table->index('eventId');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('messages');
        Schema::dropIfExists('message_records');
        Schema::dropIfExists('event_feature_balances');
        Schema::dropIfExists('event_package_subscriptions');
        Schema::dropIfExists('service_packages');
        Schema::dropIfExists('eventsTemplatePayment');
        Schema::dropIfExists('eventsPayments');
    }
};
