<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class () extends Migration {
    public function up(): void
    {
        Schema::create('comment_reactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('category_comment_id')->constrained()->cascadeOnDelete();

            // The emoji itself, closed by App\Enums\Reaction. utf8mb4 is what makes a four-byte
            // emoji storable at all; MySQL's older utf8 would truncate it.
            $table->string('emoji', 16);

            // The app has no accounts, so a reaction cannot belong to a person. It belongs to a
            // browser: a random token kept in localStorage. That is enough to make a reaction
            // toggle correctly and to stop one browser counting itself twice, and it is the
            // honest limit of what "who reacted" can mean without sign-in.
            $table->string('reactor', 64);

            $table->timestamps();

            // One reaction of a kind per browser, enforced here rather than trusted from the
            // client, so a double-tap cannot leave two rows behind.
            $table->unique(['category_comment_id', 'emoji', 'reactor']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comment_reactions');
    }
};
