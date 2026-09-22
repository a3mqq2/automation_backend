<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('facebook_pages', function (Blueprint $table) {
            $table->dateTime('comments_polled_at')->nullable()->after('connected_at');
        });
    }

    public function down(): void
    {
        Schema::table('facebook_pages', function (Blueprint $table) {
            $table->dropColumn('comments_polled_at');
        });
    }
};
