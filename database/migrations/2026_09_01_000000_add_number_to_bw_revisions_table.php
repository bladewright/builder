<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Schema;
use Illuminate\Database\Schema\Blueprint;

/**
 * Number the revisions **per page**, not across the whole table.
 *
 * The screens were showing `bw_revisions.id`, which counts every revision of
 * every page, block and layout. So a brand new site's first page opened at
 * **#3** — the layout had taken 1 and 2 — and "version 3 of a page nobody has
 * edited" is nonsense to the person reading it.
 *
 * A revision's number belongs to the thing it is a revision of. The id stays
 * what the database points at; the number is what people say.
 */
return new class extends Migration
{
    public function getConnection(): ?string
    {
        return config('bladewright.database.connection');
    }

    public function up(): void
    {
        $schema = Schema::connection($this->getConnection());

        $schema->table('bw_revisions', function (Blueprint $table) {
            $table->unsignedInteger('number')->default(0)->after('id');
            $table->index(['subject_type', 'subject_id', 'number']);
        });

        $this->numberWhatIsAlreadyThere();
    }

    public function down(): void
    {
        Schema::connection($this->getConnection())->table('bw_revisions', function (Blueprint $table) {
            $table->dropIndex(['subject_type', 'subject_id', 'number']);
            $table->dropColumn('number');
        });
    }

    /**
     * Give the existing revisions their numbers.
     *
     * In the order they were made, which is the order of the ids — the history
     * reads the same afterwards, only counted from one per subject.
     */
    private function numberWhatIsAlreadyThere(): void
    {
        $connection = \Illuminate\Support\Facades\DB::connection($this->getConnection());

        $subjects = $connection->table('bw_revisions')
            ->select('subject_type', 'subject_id')
            ->distinct()
            ->get();

        foreach ($subjects as $subject) {
            $number = 0;

            $rows = $connection->table('bw_revisions')
                ->where('subject_type', $subject->subject_type)
                ->where('subject_id', $subject->subject_id)
                ->orderBy('id')
                ->pluck('id');

            foreach ($rows as $id) {
                $connection->table('bw_revisions')->where('id', $id)->update(['number' => ++$number]);
            }
        }
    }
};
