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
        Schema::create('ticket_types', function (Blueprint $table) {
            $table->id();
            $table->foreignId('eventId')->constrained('events')->cascadeOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->decimal('price', 12, 2)->default(0.00);
            $table->string('currency', 10)->default('TZS');
            $table->integer('quantityTotal');
            $table->integer('quantitySold')->default(0);
            $table->integer('minPerOrder')->default(1);
            $table->integer('maxPerOrder')->default(10);
            $table->timestamp('salesStartAt')->nullable();
            $table->timestamp('salesEndAt')->nullable();
            $table->boolean('isActive')->default(true);
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent();

            $table->index(['eventId', 'isActive']);
        });

        Schema::create('ticket_orders', function (Blueprint $table) {
            $table->id();
            $table->string('orderNumber', 32)->unique();
            $table->foreignId('eventId')->constrained('events')->cascadeOnDelete();
            $table->foreignId('userId')->nullable()->constrained('users')->nullOnDelete();
            $table->string('customerName');
            $table->string('customerEmail');
            $table->string('customerPhone');
            $table->decimal('totalAmount', 14, 2);
            $table->decimal('paidAmount', 14, 2)->default(0.00);
            $table->string('currency', 10)->default('TZS');
            $table->string('paymentMethod')->default('mobile_money');
            $table->string('transactionId')->nullable()->index();
            $table->string('status')->default('pending'); // pending, paid, cancelled, refunded
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent();

            $table->index(['eventId', 'status']);
        });

        Schema::create('tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('orderId')->constrained('ticket_orders')->cascadeOnDelete();
            $table->foreignId('ticketTypeId')->constrained('ticket_types')->cascadeOnDelete();
            $table->foreignId('eventId')->constrained('events')->cascadeOnDelete();
            $table->string('ticketNumber', 32)->unique();
            $table->string('holderName');
            $table->string('holderEmail')->nullable();
            $table->string('holderPhone')->nullable();
            $table->string('qrToken')->unique();
            $table->string('tokenHash', 64)->unique()->index();
            $table->string('status')->default('valid'); // valid, used, cancelled, transferred
            $table->timestamp('checkedInAt')->nullable();
            $table->foreignId('checkedInByGateKeeperId')->nullable()->constrained('gateKeepers')->nullOnDelete();
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent();

            $table->index(['eventId', 'status']);
            $table->index('ticketTypeId');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('tickets');
        Schema::dropIfExists('ticket_orders');
        Schema::dropIfExists('ticket_types');
    }
};
