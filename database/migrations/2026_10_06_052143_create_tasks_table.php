<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tahap kerja proyek: satu baris per (tahap, agen). Sumber linimasa (FR-20),
        // kelanjutan setelah putus (resume dari status), dan batas delegasi (FR-24).
        Schema::create('tasks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project_id')->constrained()->cascadeOnDelete();
            $table->string('stage', 64);
            $table->string('title');
            $table->unsignedInteger('step');
            $table->foreignId('agent_id')->nullable()->constrained()->nullOnDelete();
            $table->string('status', 24)->default('queued');
            $table->json('inputs')->nullable();
            $table->foreignId('output_artifact_id')->nullable()->constrained('artifacts')->nullOnDelete();
            $table->unsignedBigInteger('tokens_in')->default(0);
            $table->unsignedBigInteger('tokens_out')->default(0);
            $table->unsignedInteger('attempts')->default(0);
            $table->unsignedInteger('depth')->default(0);
            $table->foreignId('parent_id')->nullable()->constrained('tasks')->nullOnDelete();
            $table->text('error')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['project_id', 'step']);
            $table->index(['project_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tasks');
    }
};
