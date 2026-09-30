<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            // cascadeOnDelete: the feed is read per project, so an activity row whose project is
            // gone could never be reached again. The project's tasks survive, only its log goes.
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            // A snapshot, not a foreign key: the entry has to still read correctly after the task
            // it describes is deleted, and "Deleted <title>" is the entry that needs it most.
            $table->string('task_title');
            $table->string('action', 20);
            // No updated_at: an activity entry is a fact about a moment and is never edited.
            $table->timestamp('created_at')->nullable();

            // The feed is always "this project, newest first", so both columns are in the index.
            $table->index(['category_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('activities');
    }
};
