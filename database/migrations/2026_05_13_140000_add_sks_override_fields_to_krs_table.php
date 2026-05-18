<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('krs', function (Blueprint $table) {
            $table->boolean('is_sks_override')->default(false)->after('is_locked');
            $table->text('sks_override_reason')->nullable()->after('is_sks_override');
            $table->uuid('sks_override_by')->nullable()->after('sks_override_reason');
            $table->timestamp('sks_override_at')->nullable()->after('sks_override_by');

            $table->foreign('sks_override_by')
                ->references('id')
                ->on('users')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('krs', function (Blueprint $table) {
            $table->dropForeign(['sks_override_by']);
            $table->dropColumn([
                'is_sks_override',
                'sks_override_reason',
                'sks_override_by',
                'sks_override_at',
            ]);
        });
    }
};
