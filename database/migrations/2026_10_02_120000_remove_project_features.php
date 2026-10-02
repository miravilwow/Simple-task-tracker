<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::dropIfExists('comment_reactions');
        Schema::dropIfExists('category_comments');
        Schema::dropIfExists('activities');

        Schema::table('categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn(['is_favorite', 'description', 'color', 'icon', 'deleted_at']);
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');
            $table->string('color', 20)->default('slate')->after('description');
            $table->string('icon', 40)->default('folder')->after('color');
            $table->boolean('is_favorite')->default(false)->after('icon');
            $table->foreignId('parent_id')->nullable()->after('is_favorite')
                ->constrained('categories')->nullOnDelete();
            $table->softDeletes();
        });

        Schema::create('activities', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->string('task_title');
            $table->string('action', 20);
            $table->timestamp('created_at')->nullable();

            $table->index(['category_id', 'created_at']);
        });

        Schema::create('category_comments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_id')->constrained()->cascadeOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->index(['category_id', 'created_at']);
        });

        Schema::create('comment_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_comment_id')->constrained()->cascadeOnDelete();
            $table->string('emoji', 16);
            $table->string('reactor', 64);
            $table->timestamps();

            $table->unique(['category_comment_id', 'emoji', 'reactor']);
        });
    }
};
