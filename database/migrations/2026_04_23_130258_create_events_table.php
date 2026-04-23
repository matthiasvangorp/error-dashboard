<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('issue_id')->constrained()->cascadeOnDelete();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->json('payload');
            $table->string('environment')->nullable();
            $table->string('release')->nullable();
            $table->timestamp('received_at');
            $table->timestamps();

            $table->index(['issue_id', 'received_at']);
            $table->index(['project_id', 'received_at']);
        });

        Schema::table('issues', function (Blueprint $table) {
            $table->foreign('last_event_id')->references('id')->on('events')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('issues', function (Blueprint $table) {
            $table->dropForeign(['last_event_id']);
        });

        Schema::dropIfExists('events');
    }
};
