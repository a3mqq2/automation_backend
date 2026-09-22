<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('automation_rules', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facebook_page_id')->constrained('facebook_pages')->cascadeOnDelete();
            $table->string('name');
            $table->string('trigger_type', 20);
            $table->string('match_type', 20);
            $table->json('keywords');
            $table->text('response_text')->nullable();
            $table->text('private_reply_text')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['facebook_page_id', 'trigger_type', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('automation_rules');
    }
};
