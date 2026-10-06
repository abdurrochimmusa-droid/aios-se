<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('agents', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64);
            $table->string('name');
            $table->foreignId('room_id')->constrained()->cascadeOnDelete();
            $table->foreignId('role_id')->constrained()->restrictOnDelete();
            $table->string('combo_name', 64)->nullable();
            $table->string('base_url')->nullable();
            $table->string('status', 16)->default('active');
            $table->unsignedBigInteger('token_budget')->nullable();
            $table->json('config')->nullable();
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['room_id', 'slug']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('agents');
    }
};
