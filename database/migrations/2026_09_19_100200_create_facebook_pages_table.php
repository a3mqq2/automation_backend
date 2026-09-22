<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('facebook_pages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('page_id', 64);
            $table->string('name');
            $table->string('category')->nullable();
            $table->text('picture_url')->nullable();
            $table->text('page_access_token')->nullable();
            $table->boolean('is_connected')->default(false);
            $table->dateTime('connected_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'page_id']);
            $table->index(['page_id', 'is_connected']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('facebook_pages');
    }
};
