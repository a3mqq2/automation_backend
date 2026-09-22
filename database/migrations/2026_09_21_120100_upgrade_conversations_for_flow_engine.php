<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->unsignedBigInteger('bot_flow_version_id')->nullable()->after('bot_flow_id');
            $table->string('current_node_id', 40)->nullable()->after('bot_flow_version_id');
            $table->json('awaiting')->nullable()->after('current_node_id');
            $table->json('variables')->nullable()->after('awaiting');
            $table->dateTime('automation_paused_until')->nullable()->after('variables');
            $table->dropColumn('current_flow_step');

            $table->foreign('bot_flow_version_id')->references('id')->on('bot_flow_versions')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('conversations', function (Blueprint $table) {
            $table->dropForeign(['bot_flow_version_id']);
            $table->dropColumn(['bot_flow_version_id', 'current_node_id', 'awaiting', 'variables', 'automation_paused_until']);
            $table->string('current_flow_step', 64)->nullable();
        });
    }
};
