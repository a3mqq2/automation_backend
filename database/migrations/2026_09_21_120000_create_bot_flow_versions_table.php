<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('bot_flow_versions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('bot_flow_id')->constrained('bot_flows')->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->json('definition');
            $table->dateTime('published_at');
            $table->timestamps();

            $table->unique(['bot_flow_id', 'version']);
        });

        Schema::table('bot_flows', function (Blueprint $table) {
            $table->unsignedBigInteger('published_version_id')->nullable()->after('flow_json');
            $table->foreign('published_version_id')->references('id')->on('bot_flow_versions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('bot_flows', function (Blueprint $table) {
            $table->dropForeign(['published_version_id']);
            $table->dropColumn('published_version_id');
        });

        Schema::dropIfExists('bot_flow_versions');
    }
};
