<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     * Creates a PostgreSQL function and trigger to send NOTIFY when a new message is inserted.
     */
    public function up(): void
    {
        // Only run if using PostgreSQL
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        // Create function to send NOTIFY with message data
        DB::unprepared('
            CREATE OR REPLACE FUNCTION notify_new_message()
            RETURNS TRIGGER AS $$
            BEGIN
                PERFORM pg_notify(\'new_message\', json_build_object(
                    \'id\', NEW.id,
                    \'user_id\', NEW.user_id,
                    \'content\', NEW.content,
                    \'created_at\', NEW.created_at
                )::text);
                RETURN NEW;
            END;
            $$ LANGUAGE plpgsql;
        ');

        // Create trigger that calls the function on INSERT
        DB::unprepared('
            DROP TRIGGER IF EXISTS notify_new_message_trigger ON messages;
            CREATE TRIGGER notify_new_message_trigger
            AFTER INSERT ON messages
            FOR EACH ROW
            EXECUTE FUNCTION notify_new_message();
        ');
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        // Only run if using PostgreSQL
        if (DB::connection()->getDriverName() !== 'pgsql') {
            return;
        }

        DB::unprepared('DROP TRIGGER IF EXISTS notify_new_message_trigger ON messages;');
        DB::unprepared('DROP FUNCTION IF EXISTS notify_new_message();');
    }
};

