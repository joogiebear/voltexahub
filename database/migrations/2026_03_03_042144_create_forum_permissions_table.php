<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('forum_permissions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('forum_id')->constrained()->cascadeOnDelete();
            $table->string('role_name'); // includes "guest"
            $table->boolean('can_view')->default(true);
            $table->boolean('can_post')->default(true);
            $table->boolean('can_reply')->default(true);
            $table->timestamps();
            $table->unique(['forum_id', 'role_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('forum_permissions');
    }
};
