<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coverage_reports', function (Blueprint $table) {
            $table->string('commit_author_name')->nullable()->after('commit_sha');
            $table->string('commit_author_email')->nullable()->after('commit_author_name');
        });
    }

    public function down(): void
    {
        Schema::table('coverage_reports', function (Blueprint $table) {
            $table->dropColumn(['commit_author_name', 'commit_author_email']);
        });
    }
};
