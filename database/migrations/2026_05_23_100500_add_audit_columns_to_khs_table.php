<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (!Schema::hasColumn('khs', 'updated_by')) {
            Schema::table('khs', function (Blueprint $table) {
                $table->uuid('updated_by')->nullable()->after('is_final');
            });

            Schema::table('khs', function (Blueprint $table) {
                $table->foreign('updated_by')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (!Schema::hasColumn('khs', 'finalized_by')) {
            Schema::table('khs', function (Blueprint $table) {
                $table->uuid('finalized_by')->nullable()->after('updated_by');
            });

            Schema::table('khs', function (Blueprint $table) {
                $table->foreign('finalized_by')->references('id')->on('users')->nullOnDelete();
            });
        }

        if (!Schema::hasColumn('khs', 'finalized_at')) {
            Schema::table('khs', function (Blueprint $table) {
                $table->timestamp('finalized_at')->nullable()->after('finalized_by');
            });
        }
    }

    public function down(): void
    {
        Schema::table('khs', function (Blueprint $table) {
            if (Schema::hasColumn('khs', 'updated_by')) {
                $table->dropForeign(['updated_by']);
            }

            if (Schema::hasColumn('khs', 'finalized_by')) {
                $table->dropForeign(['finalized_by']);
            }
        });

        Schema::table('khs', function (Blueprint $table) {
            $columns = [];

            if (Schema::hasColumn('khs', 'updated_by')) {
                $columns[] = 'updated_by';
            }

            if (Schema::hasColumn('khs', 'finalized_by')) {
                $columns[] = 'finalized_by';
            }

            if (Schema::hasColumn('khs', 'finalized_at')) {
                $columns[] = 'finalized_at';
            }

            if ($columns !== []) {
                $table->dropColumn($columns);
            }
        });
    }
};
