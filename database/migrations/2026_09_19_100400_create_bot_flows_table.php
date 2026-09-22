<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('bot_flows', function (Blueprint $table) {
            $table->id();
            $table->foreignId('facebook_page_id')->constrained('facebook_pages')->cascadeOnDelete();
            $table->string('name');
            $table->json('flow_json');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->index(['facebook_page_id', 'is_active']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bot_flows');
    }
};
