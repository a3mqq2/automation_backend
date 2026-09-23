<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('fb_user_id', 64)->nullable()->change();
            $table->string('password')->nullable()->after('email');
            $table->dropIndex(['email']);
            $table->unique('email');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropUnique(['email']);
            $table->index('email');
            $table->dropColumn('password');
            $table->string('fb_user_id', 64)->nullable(false)->change();
        });
    }
};
