<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('ALTER TABLE "whatsapp_user" ALTER COLUMN "agent_id" TYPE bigint USING agent_id::bigint');

        DB::statement('ALTER TABLE "whatsapp_session_status" DROP CONSTRAINT IF EXISTS whatsapp_session_status_agent_id_fkey');
        DB::statement('ALTER TABLE "whatsapp_session_status" DROP CONSTRAINT IF EXISTS whatsapp_session_status_pkey');
        DB::statement('ALTER TABLE "whatsapp_session_status" ALTER COLUMN "agent_id" TYPE bigint USING agent_id::bigint');
        DB::statement('ALTER TABLE "whatsapp_session_status" ADD PRIMARY KEY ("agent_id")');
        DB::statement('ALTER TABLE "whatsapp_session_status" ADD CONSTRAINT whatsapp_session_status_agent_id_fkey FOREIGN KEY ("agent_id") REFERENCES "whatsapp_user" ("agent_id") ON DELETE CASCADE');
    }

    public function down(): void
    {
        DB::statement('ALTER TABLE "whatsapp_session_status" DROP CONSTRAINT IF EXISTS whatsapp_session_status_agent_id_fkey');
        DB::statement('ALTER TABLE "whatsapp_session_status" DROP CONSTRAINT IF EXISTS whatsapp_session_status_pkey');
        DB::statement('ALTER TABLE "whatsapp_session_status" ALTER COLUMN "agent_id" TYPE varchar(255) USING agent_id::varchar');
        DB::statement('ALTER TABLE "whatsapp_session_status" ADD PRIMARY KEY ("agent_id")');
        DB::statement('ALTER TABLE "whatsapp_session_status" ADD CONSTRAINT whatsapp_session_status_agent_id_fkey FOREIGN KEY ("agent_id") REFERENCES "whatsapp_user" ("agent_id") ON DELETE CASCADE');

        DB::statement('ALTER TABLE "whatsapp_user" ALTER COLUMN "agent_id" TYPE varchar(255) USING agent_id::varchar');
    }
};
