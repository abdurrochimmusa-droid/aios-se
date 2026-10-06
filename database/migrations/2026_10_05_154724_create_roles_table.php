<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('roles', function (Blueprint $table) {
            $table->id();
            $table->string('slug', 64)->unique();
            $table->string('name');
            $table->text('desc')->nullable();
            $table->text('instructions');
            $table->json('skills')->nullable();
            $table->json('allowed_tools')->nullable();
            $table->json('expected_inputs')->nullable();
            $table->json('outputs')->nullable();
            $table->string('default_combo', 64)->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->boolean('is_builtin')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('roles');
    }
};
