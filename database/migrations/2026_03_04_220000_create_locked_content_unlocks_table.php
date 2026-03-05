<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('locked_content_unlocks', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('user_id');
            $table->string('content_hash', 64);
            $table->integer('credits_spent')->default(0);
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->cascadeOnDelete();
            $table->unique(['user_id', 'content_hash']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('locked_content_unlocks');
    }
};
