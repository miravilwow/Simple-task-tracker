<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CategoryComment extends Model
{
    protected $fillable = [
        'body',
    ];

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<CommentReaction, $this>
     */
    public function reactions(): HasMany
    {
        return $this->hasMany(CommentReaction::class);
    }
}
