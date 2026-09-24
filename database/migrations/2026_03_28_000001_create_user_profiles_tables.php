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
        Schema::create('systemUsers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('userId')->nullable()->constrained('users')->nullOnDelete();
            $table->string('username', 60)->nullable();
            $table->string('email')->nullable();
            $table->string('firstName');
            $table->string('middleName')->nullable();
            $table->string('lastName');
            $table->string('phone')->nullable();
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent();
        });

        Schema::create('eventOwners', function (Blueprint $table) {
            $table->id();
            $table->foreignId('userId')->nullable()->constrained('users')->nullOnDelete();
            $table->string('username', 60)->nullable();
            $table->string('firstName');
            $table->string('middleName')->nullable();
            $table->string('lastName');
            $table->string('email')->nullable();
            $table->string('phone')->nullable();
            $table->jsonb('eventIds')->default('[]');
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent();
        });

        Schema::create('gateKeepers', function (Blueprint $table) {
            $table->id();
            $table->foreignId('userId')->nullable()->constrained('users')->nullOnDelete();
            $table->string('username', 60)->nullable();
            $table->string('fullName');
            $table->string('phone')->nullable();
            $table->string('email')->nullable();
            $table->jsonb('eventIds')->default('[]');
            $table->timestamp('createdAt')->useCurrent();
            $table->timestamp('updatedAt')->useCurrent();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('gateKeepers');
        Schema::dropIfExists('eventOwners');
        Schema::dropIfExists('systemUsers');
    }
};
