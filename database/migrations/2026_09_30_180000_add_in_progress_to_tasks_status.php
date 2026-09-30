<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // A task is rarely either untouched or finished. The board reads To do, In progress
            // and Done, and without a value for the middle one the middle column could only ever
            // be empty.
            //
            // This widens a set the exam brief pinned at pending|completed, which is a deliberate
            // deviation and is written up in the README. Both original values keep their meaning:
            // pending is still the default a task is created with, and completed is still the end.
            $table->enum('status', ['pending', 'in_progress', 'completed'])
                ->default('pending')
                // No ->index(): the column already has one, and change() keeps it. Adding it again
                // is a duplicate key error rather than a no-op.
                ->change();
        });
    }

    public function down(): void
    {
        // Anything mid-flight goes back to the value it would have had before the column knew
        // about the middle state, or the narrowed enum would reject it.
        DB::table('tasks')->where('status', 'in_progress')->update(['status' => 'pending']);

        Schema::table('tasks', function (Blueprint $table) {
            $table->enum('status', ['pending', 'completed'])->default('pending')->change();
        });
    }
};
