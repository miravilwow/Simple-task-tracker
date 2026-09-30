<?php

namespace App\Models;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes;

class Task extends Model
{
    /** @use HasFactory<TaskFactory> */
    use HasFactory;

    // A deleted task is only stamped, never removed, so the UI can offer Undo.
    // Every query excludes the stamped rows through the trait's global scope.
    use SoftDeletes;

    protected $fillable = [
        'title',
        'description',
        'priority',
        'category_id',
        'due_date',
    ];

    // Mirrors the DB defaults so a freshly created model includes them in its JSON.
    protected $attributes = [
        'priority' => TaskPriority::Medium->value,
        'status' => TaskStatus::Pending->value,
    ];

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function isOverdue(): bool
    {
        return $this->status === TaskStatus::Pending
            && $this->due_date !== null
            && $this->due_date->isBefore(today());
    }

    protected function casts(): array
    {
        return [
            'priority' => TaskPriority::class,
            'status' => TaskStatus::class,
            'due_date' => 'date',
        ];
    }
}
