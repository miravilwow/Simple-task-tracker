<?php

namespace App\Http\Controllers;

use App\Http\Requests\ReactToCommentRequest;
use App\Http\Requests\StoreCategoryCommentRequest;
use App\Http\Resources\CategoryCommentResource;
use App\Models\Category;
use App\Models\CategoryComment;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryCommentController extends Controller
{
    /**
     * Oldest first: a comment thread is read from the top, unlike the activity feed, which is
     * scanned for the latest thing that happened.
     */
    public function index(Category $category): AnonymousResourceCollection
    {
        $comments = $category->comments()
            // Without this the thread would run one reaction query per comment.
            ->with('reactions')
            ->oldest('created_at')
            ->oldest('id')
            ->get();

        return CategoryCommentResource::collection($comments);
    }

    public function store(StoreCategoryCommentRequest $request, Category $category): JsonResponse
    {
        $comment = $category->comments()->create($request->validated());

        return CategoryCommentResource::make($comment->load('reactions'))->response()->setStatusCode(201);
    }

    /**
     * Adds or removes one browser's reaction of one kind.
     *
     * The unique index does the work: firstOrCreate cannot leave a second row behind, and
     * removing one that was never there is a no-op rather than an error, which is what makes
     * the endpoint safe to retry.
     */
    public function react(ReactToCommentRequest $request, Category $category, CategoryComment $comment): CategoryCommentResource
    {
        $this->assertBelongsTo($comment, $category);

        $keys = [
            'emoji' => $request->validated('emoji'),
            'reactor' => $request->validated('reactor'),
        ];

        if ($request->validated('reacted')) {
            $comment->reactions()->firstOrCreate($keys);
        } else {
            $comment->reactions()->where($keys)->delete();
        }

        return CategoryCommentResource::make($comment->load('reactions'));
    }

    public function destroy(Category $category, CategoryComment $comment): JsonResponse
    {
        $this->assertBelongsTo($comment, $category);

        $comment->delete();

        return response()->json(['message' => 'Comment deleted.']);
    }

    /**
     * A comment id from another project is a 404 rather than a hit on someone else's row.
     */
    private function assertBelongsTo(CategoryComment $comment, Category $category): void
    {
        abort_unless($comment->category_id === $category->id, 404);
    }
}
