<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Where a task sits inside its board column, once someone has dragged it there.
 *
 * TaskSorter still decides the order everywhere else. This backs TaskSort::Manual alone, which
 * the board asks for: an arrangement a person made by hand is the one thing a sort cannot derive.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->unsignedInteger('position')->default(0)->after('status');

            // Exactly how a column reads: its own tasks, in the order someone put them.
            $table->index(['status', 'position']);
        });

        // Existing rows all carry 0, which would leave a column in no particular order. Number
        // them per status so the first manual drag starts from something deliberate.
        $next = [];

        foreach (DB::table('tasks')->orderBy('id')->get(['id', 'status']) as $row) {
            $next[$row->status] ??= 0;

            DB::table('tasks')->where('id', $row->id)->update(['position' => $next[$row->status]++]);
        }
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropIndex(['status', 'position']);
            $table->dropColumn('position');
        });
    }
};
