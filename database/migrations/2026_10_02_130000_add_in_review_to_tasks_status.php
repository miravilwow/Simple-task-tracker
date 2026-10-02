<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Finished work often waits on someone else's eyes before it is done. No ->index():
            // the column already has one, and change() keeps it.
            $table->enum('status', ['pending', 'in_progress', 'in_review', 'completed'])
                ->default('pending')
                ->change();
        });
    }

    public function down(): void
    {
        DB::table('tasks')->where('status', 'in_review')->update(['status' => 'in_progress']);

        Schema::table('tasks', function (Blueprint $table) {
            $table->enum('status', ['pending', 'in_progress', 'completed'])->default('pending')->change();
        });
    }
};
