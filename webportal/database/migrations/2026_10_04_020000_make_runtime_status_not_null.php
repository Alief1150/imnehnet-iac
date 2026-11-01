<?php

use App\Enums\RuntimeStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Stop runtime_status ever being null again.
 *
 * A fatal on /student/terminal — "Call to a member function isRunning() on null" —
 * came from runtime_status holding null, and the column allowed it. The column in
 * production is nullable with no default, while the migration that creates the table
 * for a fresh install declares it NOT NULL DEFAULT 'Unknown'. The two schemas
 * disagree, which is why the test suite could not have reproduced the fault: its
 * database refused the very value that broke the page.
 *
 * Unread is a state with a name. RuntimeStatus::Unknown exists for it, and this
 * project's rule is that an unmeasured value is reported as unknown rather than
 * guessed. Null said nothing at all, which is strictly worse: it is indistinguishable
 * from a row that was never written, and it is the one value in that column with no
 * label to show.
 *
 * Existing nulls become Unknown rather than being deleted or guessed into a runtime
 * state. Unknown is the truthful reading of a row whose runtime was never observed.
 *
 * The guard added to Terminal.php stays. This migration removes the way the value
 * gets here; the guard means a row written by hand, restored from an old dump, or
 * produced by an older code path cannot take the page down either.
 */
return new class extends Migration
{
    public function up(): void
    {
        $connection = (string) config('infrastructure.environment_connection', 'imnehnet');

        if (! Schema::connection($connection)->hasTable('lab_environments')) {
            return;
        }

        $unknown = RuntimeStatus::Unknown->value;

        // Backfill before constraining: the constraint cannot be applied while rows
        // violate it, and this is the statement that makes those rows legal.
        DB::connection($connection)
            ->table('lab_environments')
            ->whereNull('runtime_status')
            ->orWhere('runtime_status', '')
            ->update(['runtime_status' => $unknown]);

        Schema::connection($connection)->table('lab_environments', function ($table): void {
            $table->string('runtime_status', 32)->default(RuntimeStatus::Unknown->value)->nullable(false)->change();
        });
    }

    public function down(): void
    {
        $connection = (string) config('infrastructure.environment_connection', 'imnehnet');

        if (! Schema::connection($connection)->hasTable('lab_environments')) {
            return;
        }

        // Deliberately not reversed. A null here is what produced a fatal on the
        // terminal page, and a migration whose rollback reintroduces a crash is
        // worse than one that leaves an extra constraint in place.
    }
};