<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            // Salinan pipeline yang boleh diubah per proyek (FR-14).
            // Null = pakai bawaan config/aios.php.
            $table->json('stages')->nullable()->after('delegation');
        });
    }

    public function down(): void
    {
        Schema::table('projects', function (Blueprint $table) {
            $table->dropColumn('stages');
        });
    }
};
