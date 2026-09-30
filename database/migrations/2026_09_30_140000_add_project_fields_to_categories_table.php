<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->text('description')->nullable()->after('name');

            // Back after being dropped once. The icon still carries the meaning; the colour is
            // the glance-level mark that tells two icons apart down a list of projects.
            // A varchar closed by App\Enums\CategoryColor, for the same reason icon is one.
            $table->string('color', 20)->default('slate')->after('description');

            $table->boolean('is_favorite')->default(false)->after('icon');

            // Archiving is reversible and deliberately not a soft delete: an archived project is
            // still a project with tasks, it is only out of the way.
            $table->timestamp('archived_at')->nullable()->after('is_favorite');

            // One tree, not two. Todoist separates folders from parent projects; here a folder
            // is simply a project with children, so "move into folder" and "set parent" are the
            // same operation. nullOnDelete, so deleting a parent promotes its children to the
            // top level rather than taking them with it.
            $table->foreignId('parent_id')->nullable()->after('archived_at')
                ->constrained('categories')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropConstrainedForeignId('parent_id');
            $table->dropColumn(['description', 'color', 'is_favorite', 'archived_at']);
        });
    }
};
