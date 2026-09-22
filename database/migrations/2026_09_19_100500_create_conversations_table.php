<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('conversations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facebook_page_id')->constrained('facebook_pages')->cascadeOnDelete();
            $table->string('psid', 64);
            $table->foreignId('bot_flow_id')->nullable()->constrained('bot_flows')->nullOnDelete();
            $table->string('current_flow_step', 64)->nullable();
            $table->dateTime('last_message_at')->nullable();
            $table->timestamps();

            $table->unique(['facebook_page_id', 'psid']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('conversations');
    }
};
