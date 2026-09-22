<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            // Set atomically by DispatchAlertJob before sending, so an issue
            // can never be alerted more than once (even across job retries).
            $table->timestamp('alerted_at')->nullable()->after('last_event_id');
        });
    }

    public function down(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->dropColumn('alerted_at');
        });
    }
};
