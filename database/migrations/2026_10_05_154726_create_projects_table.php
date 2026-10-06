<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 128)->unique();
            $table->string('name');
            $table->text('idea')->nullable();
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->string('status', 16)->default('draft');
            $table->string('repo_path')->nullable();
            $table->unsignedBigInteger('token_budget')->nullable();
            $table->json('delegation')->nullable();
            $table->foreignId('owner_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->index(['room_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
