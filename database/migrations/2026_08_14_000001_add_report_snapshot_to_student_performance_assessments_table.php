<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_performance_assessments', function (Blueprint $table) {
            $table->json('report_snapshot')->nullable()->after('percentage');
            $table->index(['assessor_role', 'assessed_at'], 'performance_assessments_role_date_index');
        });
    }

    public function down(): void
    {
        Schema::table('student_performance_assessments', function (Blueprint $table) {
            $table->dropIndex('performance_assessments_role_date_index');
            $table->dropColumn('report_snapshot');
        });
    }
};
