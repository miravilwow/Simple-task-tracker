<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            // Archiving and deleting had grown into the same thing: both hid a project and both
            // could be undone. Two names for one action meant two endpoints, two lists in the
            // sidebar and two places to look for a project that is not where you left it.
            //
            // Soft delete is the one that stays, because it is the one that also protects
            // against a mistake. What archiving had that it lacked was somewhere permanent to
            // look, so the sidebar's Archived section became a Deleted section.
            $table->dropColumn('archived_at');
        });
    }

    public function down(): void
    {
        Schema::table('categories', function (Blueprint $table) {
            $table->timestamp('archived_at')->nullable()->after('is_favorite');
        });
    }
};
