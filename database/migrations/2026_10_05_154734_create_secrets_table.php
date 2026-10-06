<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('secrets', function (Blueprint $table) {
            $table->id();
            $table->string('scope', 16)->default('global');
            $table->unsignedBigInteger('scope_id')->nullable();
            $table->string('key_name', 128);
            $table->text('value');
            $table->timestamps();

            $table->unique(['scope', 'scope_id', 'key_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('secrets');
    }
};
