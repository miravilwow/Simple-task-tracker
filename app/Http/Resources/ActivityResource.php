<?php

namespace App\Http\Resources;

use App\Models\Activity;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Activity
 */
class ActivityResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'action' => $this->action->value,
            // The verb belongs to the enum, so the feed cannot word an action differently to the UI.
            'label' => $this->action->label(),
            'task_title' => $this->task_title,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
