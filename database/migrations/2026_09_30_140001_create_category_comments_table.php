<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('category_comments', function (Blueprint $table) {
            $table->id();
            // Named for what it comments on. A bare "comments" table would be ambiguous the day
            // a task gets comments too.
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();

            // The thread is always "this project, oldest first", so both columns are in the index.
            $table->index(['category_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('category_comments');
    }
};
