<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('whatsapp_session_status', function (Blueprint $table) {
            $table->string('agent_id')->primary();
            $table->string('status');
            $table->timestampTz('last_connected_at')->nullable();
            $table->timestampTz('last_disconnected_at')->nullable();
            $table->timestampTz('updated_at')->useCurrent()->useCurrentOnUpdate();

            $table->foreign('agent_id', 'whatsapp_session_status_agent_id_fkey')
                ->references('agent_id')
                ->on('whatsapp_user')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_session_status');
    }
};
