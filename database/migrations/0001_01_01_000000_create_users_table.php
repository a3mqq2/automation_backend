<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table) {
            $table->id();
            $table->string('fb_user_id', 64)->unique();
            $table->string('name');
            $table->string('email')->nullable()->index();
            $table->text('avatar_url')->nullable();
            $table->text('fb_access_token')->nullable();
            $table->dateTime('token_expires_at')->nullable();
            $table->dateTime('subscription_expires_at')->nullable()->index();
            $table->unsignedBigInteger('active_license_key_id')->nullable();
            $table->dateTime('last_login_at')->nullable();
            $table->timestamps();
        });

        Schema::create('sessions', function (Blueprint $table) {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable()->index();
            $table->string('ip_address', 45)->nullable();
            $table->text('user_agent')->nullable();
            $table->longText('payload');
            $table->integer('last_activity')->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sessions');
        Schema::dropIfExists('users');
    }
};
