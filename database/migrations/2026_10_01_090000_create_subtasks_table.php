<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    /**
     * A sub-task is a checklist item on one task, not a task of its own: it never reaches the
     * list, the board, the calendar or the stats. Its own table keeps it that way, where a
     * self-referencing tasks.parent_id would have made every read and every count ask whether
     * it meant tasks or sub-tasks too.
     */
    public function up(): void
    {
        Schema::create('subtasks', function (Blueprint $table) {
            $table->id();
            // Cascade, not nullOnDelete: an item with no task can never be reached again.
            $table->foreignId('task_id')->constrained()->cascadeOnDelete();
            $table->string('title');
            $table->boolean('is_done')->default(false);
            $table->unsignedInteger('position')->default(0);
            $table->timestamps();

            $table->index(['task_id', 'position']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('subtasks');
    }
};
