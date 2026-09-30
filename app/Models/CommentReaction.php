<?php

namespace App\Models;

use App\Enums\Reaction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CommentReaction extends Model
{
    protected $fillable = [
        'emoji',
        'reactor',
    ];

    /**
     * @return BelongsTo<CategoryComment, $this>
     */
    public function comment(): BelongsTo
    {
        return $this->belongsTo(CategoryComment::class, 'category_comment_id');
    }

    protected function casts(): array
    {
        return [
            'emoji' => Reaction::class,
        ];
    }
}
