<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->unsignedBigInteger('letsdothis_ticket_id')->nullable()->after('last_event_id');
            $table->string('letsdothis_ticket_url')->nullable()->after('letsdothis_ticket_id');
        });
    }

    public function down(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->dropColumn(['letsdothis_ticket_id', 'letsdothis_ticket_url']);
        });
    }
};
