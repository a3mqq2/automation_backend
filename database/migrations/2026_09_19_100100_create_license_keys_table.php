<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('license_keys', function (Blueprint $table) {
            $table->id();
            $table->string('key', 32)->unique();
            $table->dateTime('expires_at')->index();
            $table->boolean('is_used')->default(false)->index();
            $table->foreignId('used_by')->nullable()->constrained('users')->nullOnDelete();
            $table->dateTime('used_at')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('admins')->nullOnDelete();
            $table->string('note', 500)->nullable();
            $table->timestamps();
        });

        Schema::table('users', function (Blueprint $table) {
            $table->foreign('active_license_key_id')->references('id')->on('license_keys')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropForeign(['active_license_key_id']);
        });

        Schema::dropIfExists('license_keys');
    }
};
