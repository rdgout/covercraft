<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('coverage_reports', function (Blueprint $table): void {
            $table->string('diff_file_path')->nullable();
            $table->string('diff_index_path')->nullable();
            $table->char('diff_sha256', 64)->nullable();
            $table->char('diff_base_sha', 40)->nullable();
            $table->string('diff_status')->nullable();
            $table->string('diff_error_code')->nullable();
            $table->json('diff_metadata')->nullable();
            $table->json('patch_coverage')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('coverage_reports', function (Blueprint $table): void {
            $table->dropColumn(['diff_file_path', 'diff_index_path', 'diff_sha256', 'diff_base_sha', 'diff_status', 'diff_error_code', 'diff_metadata', 'patch_coverage']);
        });
    }
};
