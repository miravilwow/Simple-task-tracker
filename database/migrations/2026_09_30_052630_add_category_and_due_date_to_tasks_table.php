<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            // Deleting a category keeps its tasks; they simply become uncategorised.
            $table->foreignId('category_id')->nullable()->after('description')
                ->constrained()->nullOnDelete();
            $table->date('due_date')->nullable()->after('status')->index();
        });
    }

    public function down(): void
    {
        Schema::table('tasks', function (Blueprint $table) {
            $table->dropConstrainedForeignId('category_id');
            $table->dropColumn('due_date');
        });
    }
};
