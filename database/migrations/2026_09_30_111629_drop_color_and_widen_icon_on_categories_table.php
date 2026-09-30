<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            // The icon replaced the colour dot outright: two ways to mark the same project is
            // one more decision than the sidebar is worth.
            $table->dropColumn('color');

            // Widened from a database enum to a varchar. The set grew to 224 icons, which would
            // make an unreadable schema, so App\Enums\CategoryIcon closes it through Rule::enum().
            $table->string('icon', 40)->default('folder')->change();
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->enum('color', ['slate', 'red', 'amber', 'green', 'blue', 'violet', 'pink'])
                ->default('slate')->after('name');
            $table->enum('icon', [
                'folder', 'briefcase', 'person', 'hat-graduation', 'book', 'home', 'cart', 'heart', 'dumbbell',
                'money', 'airplane', 'food', 'games', 'people', 'lightbulb', 'calendar', 'code', 'paint-brush',
                'vehicle-car', 'animal-paw-print', 'music-note-2', 'pill', 'gift', 'leaf',
            ])->default('folder')->change();
        });
    }
};
