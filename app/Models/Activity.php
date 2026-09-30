<?php

namespace App\Models;

use App\Enums\ActivityAction;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Activity extends Model
{
    public const UPDATED_AT = null;

    protected $fillable = [
        'category_id',
        'task_title',
        'action',
    ];

    /**
     * Writes one entry for something that happened to a task.
     *
     * A task with no project is skipped: the feed is only ever read from a project's menu, so the
     * entry would be stored and never reachable.
     */
    public static function record(Task $task, ActivityAction $action): void
    {
        if ($task->category_id === null) {
            return;
        }

        static::create([
            'category_id' => $task->category_id,
            'task_title' => $task->title,
            'action' => $action,
        ]);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    protected function casts(): array
    {
        return [
            'action' => ActivityAction::class,
            'created_at' => 'datetime',
        ];
    }
}
