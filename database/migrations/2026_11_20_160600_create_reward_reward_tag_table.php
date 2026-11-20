<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reward_reward_tag', function (Blueprint $table) {
            $table->foreignId('reward_id')->constrained('rewards')->cascadeOnDelete();
            $table->foreignId('reward_tag_id')->constrained('reward_tags')->cascadeOnDelete();
            $table->primary(['reward_id', 'reward_tag_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reward_reward_tag');
    }
};
