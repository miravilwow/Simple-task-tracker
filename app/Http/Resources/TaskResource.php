<?php

namespace App\Http\Resources;

use App\Models\Task;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Task
 */
class TaskResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'priority' => $this->priority->value,
            'status' => $this->status->value,
            'color' => $this->color?->value,
            // Where the task sits in its board column. The board sends it back when a card is
            // dropped between two others.
            'position' => $this->position,
            'due_date' => $this->due_date?->toDateString(),
            // `H:i`, or null. The day is what decides lateness, so this is detail and never a
            // reason a task counts as overdue.
            'due_time' => $this->due_time,
            'is_overdue' => $this->isOverdue(),
            'category' => CategoryResource::make($this->whenLoaded('category')),
            // Only the task dialog loads these; the list and the board never ask for them.
            'subtasks' => SubtaskResource::collection($this->whenLoaded('subtasks')),
            'created_at' => $this->created_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
