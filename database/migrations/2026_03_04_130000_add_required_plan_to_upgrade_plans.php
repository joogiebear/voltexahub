<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('upgrade_plans', function (Blueprint $table) {
            $table->unsignedBigInteger('required_plan_id')->nullable()->after('is_featured');
            $table->foreign('required_plan_id')->references('id')->on('upgrade_plans')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('upgrade_plans', function (Blueprint $table) {
            $table->dropForeign(['required_plan_id']);
            $table->dropColumn('required_plan_id');
        });
    }
};
