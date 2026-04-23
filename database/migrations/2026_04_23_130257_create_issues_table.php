<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('issues', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->char('fingerprint', 64);
            $table->string('title');
            $table->string('type', 16);
            $table->string('level', 16);
            $table->string('status', 16)->default('open');
            $table->string('environment')->nullable();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->unsignedBigInteger('occurrence_count')->default(0);
            $table->unsignedBigInteger('last_event_id')->nullable();
            $table->timestamps();

            $table->unique(['project_id', 'fingerprint']);
            $table->index(['project_id', 'status', 'last_seen_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('issues');
    }
};
