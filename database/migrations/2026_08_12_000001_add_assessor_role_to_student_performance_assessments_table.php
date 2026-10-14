<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('student_performance_assessments', function (Blueprint $table) {
            if (! Schema::hasColumn('student_performance_assessments', 'assessor_role')) {
                $table->string('assessor_role', 32)->nullable()->after('assessor_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('student_performance_assessments', function (Blueprint $table) {
            if (Schema::hasColumn('student_performance_assessments', 'assessor_role')) {
                $table->dropColumn('assessor_role');
            }
        });
    }
};
