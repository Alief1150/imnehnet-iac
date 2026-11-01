<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private function connectionName(): string
    {
        return (string) config(
            'infrastructure.environment_connection',
            'imnehnet'
        );
    }

    private function hasIndex(string $name): bool
    {
        // The suite runs on sqlite while production is MySQL, so the index
        // list comes from the schema builder rather than SHOW INDEX.
        return collect(DB::connection($this->connectionName())
            ->getSchemaBuilder()
            ->getIndexes('lab_sessions'))
            ->contains(fn (array $index): bool => ($index['name'] ?? null) === $name);
    }

    public function up(): void
    {
        $db = DB::connection($this->connectionName());

        /*
         * MySQL drops an index inside ALTER TABLE; sqlite rewrites the table
         * instead. The suite runs on sqlite, so the unsupported branch is
         * skipped there rather than written in a dialect it cannot parse.
         */
        $mysql = $db->getDriverName() === 'mysql';

        /*
         * Deleted sessions return NULL.
         * MySQL permits multiple NULLs in a UNIQUE index.
         * Every non-deleted session retains its user_id.
         */

        if (! Schema::connection($this->connectionName())
            ->hasColumn('lab_sessions', 'active_user_id')) {

            $db->statement("
                ALTER TABLE lab_sessions
                ADD COLUMN active_user_id BIGINT UNSIGNED
                GENERATED ALWAYS AS (
                    CASE
                        WHEN status = 'DELETED' THEN NULL
                        ELSE user_id
                    END
                ) STORED
            ");
        }

        /*
         * Install the new safety constraint FIRST.
         * If duplicate active sessions exist, this fails
         * without removing the old constraint.
         */

        if (! $this->hasIndex('lab_sessions_active_user_unique')) {
            $db->statement("
                CREATE UNIQUE INDEX lab_sessions_active_user_unique
                ON lab_sessions (active_user_id)
            ");
        }

        /*
         * Remove only the legacy constraint that prevents
         * historical sessions from reusing container names.
         */

        if ($mysql && $this->hasIndex(
            'lab_sessions_user_id_course_id_attacker_container_unique'
        )) {
            $db->statement("
                ALTER TABLE lab_sessions
                DROP INDEX
                lab_sessions_user_id_course_id_attacker_container_unique
            ");
        }
    }

    public function down(): void
    {
        $db = DB::connection($this->connectionName());

        /*
         * Restoring the old constraint is only safe when
         * no historical duplicates exist.
         */

        $duplicates = $db->table('lab_sessions')
            ->select('user_id', 'course_id', 'attacker_container')
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('user_id', 'course_id', 'attacker_container')
            ->havingRaw('COUNT(*) > 1')
            ->exists();

        if ($duplicates) {
            throw new RuntimeException(
                'Cannot restore legacy unique index: historical ' .
                'sessions now share container names.'
            );
        }

        if (! $this->hasIndex(
            'lab_sessions_user_id_course_id_attacker_container_unique'
        )) {
            $db->statement("
                CREATE UNIQUE INDEX
                lab_sessions_user_id_course_id_attacker_container_unique
                ON lab_sessions (
                    user_id,
                    course_id,
                    attacker_container
                )
            ");
        }

        if ($mysql && $this->hasIndex('lab_sessions_active_user_unique')) {
            $db->statement("
                ALTER TABLE lab_sessions
                DROP INDEX lab_sessions_active_user_unique
            ");
        }

        if (Schema::connection($this->connectionName())
            ->hasColumn('lab_sessions', 'active_user_id')) {
            $db->statement("
                ALTER TABLE lab_sessions
                DROP COLUMN active_user_id
            ");
        }
    }
};
