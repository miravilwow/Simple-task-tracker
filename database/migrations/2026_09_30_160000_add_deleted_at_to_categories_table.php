<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            // Deleting a project was the one irreversible action left in the app, while deleting
            // a single task inside it could be undone from a toast. A project holds tasks, its
            // comments, its activity and its children, so it was the worst thing to lose and the
            // least protected. Stamping the row instead lets Undo reach it.
            //
            // Soft deleting also stops the foreign keys firing: tasks keep their category_id and
            // children keep their parent_id while the row is hidden, which is what makes a
            // restore put everything back where it was rather than leaving it orphaned.
            $table->softDeletes();
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropSoftDeletes();
        });
    }
};
