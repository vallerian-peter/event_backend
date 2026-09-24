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
        Schema::create('templates', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Template');
            $table->string('pathUrl');
            $table->string('previewImageUrl')->nullable();
            $table->decimal('price', 10, 2)->default(0.00);
            $table->boolean('isPremium')->default(false);
            $table->string('category')->nullable();
            $table->string('templateType')->default('invitation_card');
            $table->integer('canvasWidth')->default(1080);
            $table->integer('canvasHeight')->default(1920);
            $table->string('renderMode')->default('qr_and_name');
            $table->boolean('isActive')->default(true);
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent();
        });

        Schema::create('template_elements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('templateId')->constrained('templates')->cascadeOnDelete();
            $table->string('elementType'); // qr_code, invitee_name, custom_text, ticket_tier, seat_number
            $table->integer('positionX');
            $table->integer('positionY');
            $table->integer('width')->nullable();
            $table->integer('height')->nullable();
            $table->integer('fontSize')->nullable()->default(36);
            $table->string('fontColor', 32)->nullable()->default('#1E293B');
            $table->string('fontFamily', 64)->nullable()->default('Outfit');
            $table->string('alignment', 20)->default('center');
            $table->boolean('isRequired')->default(true);
            $table->boolean('isActive')->default(true);
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent();

            $table->index(['templateId', 'elementType']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('template_elements');
        Schema::dropIfExists('templates');
    }
};
