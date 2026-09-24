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
        Schema::table('events', function (Blueprint $table) {
            $table->foreignId('eventOwnerId')->nullable()->after('templateId')->constrained('eventOwners')->nullOnDelete();
            $table->string('flyerUrl')->nullable()->after('bannerUrl');

            $table->index('eventOwnerId');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('events', function (Blueprint $table) {
            $table->dropForeign(['eventOwnerId']);
            $table->dropIndex(['eventOwnerId']);
            $table->dropColumn(['eventOwnerId', 'flyerUrl']);
        });
    }
};
