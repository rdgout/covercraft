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
        Schema::create('coverage_files', function (Blueprint $table) {
            $table->id();
            $table->foreignId('coverage_report_id')->constrained()->cascadeOnDelete();
            $table->string('file_path', 500);
            $table->integer('total_lines');
            $table->integer('covered_lines');
            $table->decimal('coverage_percentage', 5, 2);
            $table->binary('line_coverage_data');
            $table->timestamps();

            $table->index(['coverage_report_id', 'file_path']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('coverage_files');
    }
};
