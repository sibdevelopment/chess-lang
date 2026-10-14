<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_performance_assessments', function (Blueprint $table) {
            $table->date('period_start')->nullable()->after('assessed_at');
            $table->date('period_end')->nullable()->after('period_start');
            $table->index(
                ['student_id', 'period_start', 'period_end'],
                'performance_assessments_student_period_index'
            );
        });
    }

    public function down(): void
    {
        Schema::table('student_performance_assessments', function (Blueprint $table) {
            $table->dropIndex('performance_assessments_student_period_index');
            $table->dropColumn(['period_start', 'period_end']);
        });
    }
};
