<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('channel', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('agent_id')->constrained('agent')->cascadeOnDelete();
            $table->string('channel_name', 32);
            $table->text('session_name');
            $table->text('qr')->nullable();
            $table->string('status', 32)->default('disconnected');
            $table->text('webhook')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->index('user_id', 'idx_channel_user_id');
            $table->index('agent_id', 'idx_channel_agent_id');
            $table->unique(['agent_id', 'channel_name'], 'uq_channel_per_agent');
        });

        DB::statement(<<<'SQL'
            ALTER TABLE channel
            ALTER COLUMN channel_name TYPE channel_name
            USING channel_name::channel_name;
        SQL);

        DB::statement(<<<'SQL'
            ALTER TABLE channel
            ALTER COLUMN status TYPE channel_status
            USING status::channel_status;
        SQL);

        DB::statement("ALTER TABLE channel ALTER COLUMN status SET DEFAULT 'disconnected'::channel_status");
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('channel');
    }
};
