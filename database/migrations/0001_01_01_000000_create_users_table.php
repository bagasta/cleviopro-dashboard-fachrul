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
        Schema::create('users', function (Blueprint $table) {
            $table->bigIncrements('id');
            $table->text('nama');
            $table->text('phone_number')->unique();
            $table->text('password');
            $table->string('status', 32)->default('gratis');
            $table->timestampTz('created_at')->useCurrent();
            $table->timestampTz('updated_at')->useCurrent()->useCurrentOnUpdate();
        });

        DB::statement(<<<'SQL'
            ALTER TABLE users
            ALTER COLUMN status TYPE user_status
            USING status::user_status;
        SQL);

        DB::statement("ALTER TABLE users ALTER COLUMN status SET DEFAULT 'gratis'::user_status");

        DB::statement(<<<'SQL'
            DO $$
            BEGIN
                IF NOT EXISTS (
                    SELECT 1
                    FROM pg_constraint
                    WHERE conname = 'password_is_bcrypt'
                      AND conrelid = 'users'::regclass
                ) THEN
                    ALTER TABLE users
                    ADD CONSTRAINT password_is_bcrypt CHECK (
                        password ~ '^\$2[aby]\$\d{2}\$[./A-Za-z0-9]{53}$'
                    );
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
        Schema::dropIfExists('users');
    }
};
