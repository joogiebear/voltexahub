<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('cover_photo_path')->nullable();
            $table->text('custom_css')->nullable();
            $table->string('username_color', 20)->nullable();
            $table->unsignedSmallInteger('userbar_hue')->nullable();
            $table->timestamp('username_changed_at')->nullable();
            $table->json('awards_sort_order')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'cover_photo_path',
                'custom_css',
                'username_color',
                'userbar_hue',
                'username_changed_at',
                'awards_sort_order',
            ]);
        });
    }
};
