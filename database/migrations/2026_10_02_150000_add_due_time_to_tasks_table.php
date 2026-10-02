<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The time of day a task is due, beside the day it is due on.
 *
 * It is its own nullable column rather than `due_date` becoming a datetime, because the day is
 * what every view in the app asks about: Overdue, Today, Upcoming, the stats and the calendar's
 * grid all compare days, and `tasks_due_date_index` is what makes those comparisons cheap. A time
 * is extra detail on a task, not a new way of deciding whether one is late, so it is left out of
 * all of them. It is unindexed for the same reason: nothing filters or sorts by it.
 */
return new class () extends Migration {
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->time('due_time')->nullable()->after('due_date');
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropColumn('due_time');
        });
    }
};
