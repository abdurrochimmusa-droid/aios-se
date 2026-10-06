<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('artifact_links', function (Blueprint $table) {
            $table->id();
            $table->foreignId('from_artifact_id')->constrained('artifacts')->cascadeOnDelete();
            $table->foreignId('to_artifact_id')->constrained('artifacts')->cascadeOnDelete();
            $table->string('relation', 32)->default('derived_from');
            $table->timestamps();

            $table->unique(['from_artifact_id', 'to_artifact_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('artifact_links');
    }
};
