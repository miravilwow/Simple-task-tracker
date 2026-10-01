<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subtask extends Model
{
    protected $fillable = [
        'title',
        'is_done',
    ];

    // Mirrors the DB default so a freshly created model includes it in its JSON.
    protected $attributes = [
        'is_done' => false,
    ];

    /**
     * @return BelongsTo<Task, $this>
     */
    public function task(): BelongsTo
    {
        return $this->belongsTo(Task::class);
    }

    protected function casts(): array
    {
        return [
            'is_done' => 'boolean',
        ];
    }
}
