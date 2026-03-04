<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('upgrade_plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->string('color', 20)->default('#8b5cf6');
            $table->decimal('price', 8, 2)->default(0);
            $table->enum('term', ['lifetime', 'monthly', 'yearly'])->default('lifetime');
            $table->string('role_name')->nullable(); // role to assign on purchase
            $table->integer('rep_power_pos')->default(1);  // +X per rep given
            $table->integer('rep_power_neg')->default(1);  // -X per rep given
            $table->integer('rep_daily_limit')->default(5); // how many reps per day
            $table->json('features')->nullable();   // [{type,label,value,icon}]
            $table->json('one_time_bonus')->nullable(); // {credits, label}
            $table->string('stripe_price_id')->nullable();
            $table->integer('display_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->boolean('is_featured')->default(false);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('upgrade_plans');
    }
};
