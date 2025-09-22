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
        Schema::create('whatsapp_user', function (Blueprint $table) {
            $table->increments('id');
            $table->bigInteger('user_id');
            $table->string('agent_id');
            $table->text('api_key');
            $table->string('session_name');
            $table->text('endpoint_url_run');
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent()->useCurrentOnUpdate();
            $table->string('status')->default('awaiting_qr');
            $table->timestampTz('last_connected_at')->nullable();
            $table->timestampTz('last_disconnected_at')->nullable();

            $table->unique('agent_id', 'whatsapp_user_agent_id_key');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('whatsapp_user');
    }
};
