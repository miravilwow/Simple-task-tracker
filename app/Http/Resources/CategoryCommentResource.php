<?php

namespace App\Http\Resources;

use App\Models\CategoryComment;
use App\Models\CommentReaction;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin CategoryComment
 */
class CategoryCommentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'body' => $this->body,
            'created_at' => $this->created_at?->toIso8601String(),
            // input(), not query(): the thread sends the token in the query string, while a
            // reaction sends it in the body, and the reply to a reaction has to know that the
            // browser that just reacted is the one asking.
            'reactions' => $this->summariseReactions($request->input('reactor')),
        ];
    }

    /**
     * One entry per emoji that anyone used, with how many browsers used it and whether this one
     * did. `mine` is worked out here rather than trusted from the client, so the button's
     * pressed state and the row in the database can never disagree.
     *
     * @return array<int, array{emoji: string, count: int, mine: bool}>
     */
    private function summariseReactions(?string $reactor): array
    {
        return $this->reactions
            ->groupBy(fn (CommentReaction $reaction) => $reaction->emoji->value)
            ->map(fn ($group, string $emoji) => [
                'emoji' => $emoji,
                'count' => $group->count(),
                'mine' => $reactor !== null
                    && $group->contains(fn (CommentReaction $one) => $one->reactor === $reactor),
            ])
            ->values()
            ->all();
    }
}
