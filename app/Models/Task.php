<?php

namespace App\Models;

use App\Enums\TaskColor;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use Database\Factories\TaskFactory;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
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
        'due_date',
        'due_time',
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

    /**
     * @return HasMany<Subtask, $this>
     */
    public function subtasks(): HasMany
    {
        return $this->hasMany(Subtask::class)->orderBy('position')->orderBy('id');
    }

    public function isOverdue(): bool
    {
        // Unfinished, not pending: starting a task does not stop it from being late.
        return in_array($this->status->value, TaskStatus::unfinished(), true)
            && $this->due_date !== null
            && $this->due_date->isBefore(today());
    }

    /**
     * Read back as `H:i` whatever the driver stored: MySQL answers a `time` column with
     * `10:30:00` and SQLite answers with exactly what was written, so without this the API
     * would hand the UI two different shapes for the same value.
     */
    protected function dueTime(): Attribute
    {
        return Attribute::make(
            get: fn (?string $value) => $value === null ? null : substr($value, 0, 5),
            set: fn (?string $value) => $value === null ? null : substr($value, 0, 5) . ':00',
        );
    }

    /**
     * A new task joins the end of its column, the way a line is joined. Doing it here rather than
     * in the controller covers the factory and the seeder too, so no creation path leaves a column
     * with several tasks all claiming position 0.
     */
    protected static function booted(): void
    {
        static::creating(function (Task $task) {
            $task->position ??= (int) static::withTrashed()
                ->where('status', $task->status ?? TaskStatus::Pending->value)
                ->max('position') + 1;
        });

        // A time with no day to sit on means nothing, so clearing the date clears it. Here rather
        // than in the controller, so every path that takes a date off a task is covered by it.
        static::saving(function (Task $task) {
            if ($task->due_date === null) {
                $task->due_time = null;
            }
        });
    }

    protected function casts(): array
    {
        return [
            'priority' => TaskPriority::class,
            'color' => TaskColor::class,
            'status' => TaskStatus::class,
            'due_date' => 'date',
            'position' => 'integer',
        ];
    }
}
