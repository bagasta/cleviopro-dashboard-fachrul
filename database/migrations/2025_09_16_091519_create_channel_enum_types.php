<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_type WHERE typname = 'channel_name'
                ) THEN
                    CREATE TYPE channel_name AS ENUM ('whatsapp', 'instagram', 'facebook');
                END IF;
            END
            $$;
        SQL);

        DB::statement(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1 FROM pg_type WHERE typname = 'channel_status'
                ) THEN
                    CREATE TYPE channel_status AS ENUM ('connected', 'disconnected');
                END IF;
            END
            $$;
        SQL);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        DB::statement(<<<'SQL'
            DO $$
            BEGIN
                IF EXISTS (
                    SELECT 1 FROM pg_type WHERE typname = 'channel_status'
                ) THEN
                    DROP TYPE channel_status;
                END IF;
            END
            $$;
        SQL);

        DB::statement(<<<'SQL'
            DO $$
            BEGIN
                IF EXISTS (
                    SELECT 1 FROM pg_type WHERE typname = 'channel_name'
                ) THEN
                    DROP TYPE channel_name;
                END IF;
            END
            $$;
        SQL);
    }
};
