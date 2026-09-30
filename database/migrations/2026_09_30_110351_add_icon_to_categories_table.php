<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            // Literal values, because a migration is a snapshot of the schema at this point in
            // time; App\Enums\CategoryIcon is what the application validates against.
            $table->enum('icon', [
                'folder', 'briefcase', 'person', 'hat-graduation', 'book', 'home', 'cart', 'heart', 'dumbbell',
                'money', 'airplane', 'food', 'games', 'people', 'lightbulb', 'calendar', 'code', 'paint-brush',
                'vehicle-car', 'animal-paw-print', 'music-note-2', 'pill', 'gift', 'leaf',
            ])->default('folder')->after('color');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->dropColumn('icon');
        });
    }
};
