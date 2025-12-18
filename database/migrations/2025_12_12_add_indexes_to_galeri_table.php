<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('galeri', function (Blueprint $table) {
            // Index untuk sorting (created_at paling sering di-sort)
            $table->index('created_at');
            
            // Index untuk filtering kategori
            $table->index('kategori');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('galeri', function (Blueprint $table) {
            $table->dropIndex(['created_at']);
            $table->dropIndex(['kategori']);
        });
    }
};
