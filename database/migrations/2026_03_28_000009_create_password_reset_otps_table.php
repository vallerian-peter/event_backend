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
        Schema::create('password_reset_otps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('userId')->constrained('users')->cascadeOnDelete();
            $table->string('phone', 30);
            $table->string('otp', 10);
            $table->boolean('isVerified')->default(false);
            $table->timestamp('verifiedAt')->nullable();
            $table->boolean('isUsed')->default(false);
            $table->timestamp('expiresAt');
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent();

            $table->index(['userId', 'isUsed', 'isVerified']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('password_reset_otps');
    }
};
