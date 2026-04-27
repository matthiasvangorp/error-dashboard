<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->string('letsdothis_base_url')->nullable()->after('alert_channels');
            $table->text('letsdothis_project_token')->nullable()->after('letsdothis_base_url');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn(['letsdothis_base_url', 'letsdothis_project_token']);
        });
    }
};
