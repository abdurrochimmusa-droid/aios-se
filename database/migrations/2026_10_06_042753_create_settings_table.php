<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Pengaturan runtime yang diubah lewat UI (pengganti .env untuk operasional).
        // .env tetap jadi bawaan awal/CT provisioning; nilai di sini menimpa config.
        Schema::create('settings', function (Blueprint $table) {
            $table->id();
            $table->string('key', 128)->unique();
            $table->text('value')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('settings');
    }
};
