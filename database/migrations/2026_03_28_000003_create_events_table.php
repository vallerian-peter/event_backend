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
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->string('title');
            $table->string('slug')->unique();
            $table->string('eventCategory')->default('normal'); // 'normal' | 'ticketed'
            $table->boolean('isPublic')->default(false);
            $table->boolean('isFeatured')->default(false);
            $table->string('category')->nullable();
            $table->string('status')->default('draft');
            $table->string('venueName')->nullable();
            $table->string('address')->nullable();
            $table->date('startDate')->nullable();
            $table->date('endDate')->nullable();
            $table->time('eventTime')->default('00:00:00');
            $table->string('contact1')->nullable();
            $table->string('contact2')->nullable();
            $table->text('description')->nullable();
            $table->string('coverImageUrl')->nullable();
            $table->string('bannerUrl')->nullable();
            $table->string('typeOfEvent')->nullable();
            $table->foreignId('templateId')->nullable()->constrained('templates')->nullOnDelete();
            $table->boolean('autoSendSms')->default(false);
            $table->text('smsTimeDifference')->default('5days');
            $table->string('divisions')->nullable();
            $table->jsonb('divisionsPrices')->nullable();
            $table->boolean('contributionsNeeded')->default(true);
            $table->integer('inviteeLimit')->default(200);
            $table->string('servicePackageSlug')->default('smart_reminder');
            $table->jsonb('serviceFeatures')->default('{}');
            $table->string('invitationTemplateTier', 20)->nullable();
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent();

            $table->index('eventCategory');
            $table->index('status');
            $table->index('isPublic');
            $table->index(['startDate', 'endDate']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('events');
    }
};
