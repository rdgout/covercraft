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
        Schema::table('coverage_reports', function (Blueprint $table) {
            $table->unsignedInteger('pr_number')->nullable()->after('commit_sha');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('coverage_reports', function (Blueprint $table) {
            $table->dropColumn('pr_number');
        });
    }
};
