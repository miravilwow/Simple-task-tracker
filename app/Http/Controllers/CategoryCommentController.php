<?php

namespace App\Http\Controllers;

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
        return CategoryCommentResource::collection(
            $category->comments()->oldest('created_at')->oldest('id')->get()
        );
    }

    public function store(StoreCategoryCommentRequest $request, Category $category): JsonResponse
    {
        $comment = $category->comments()->create($request->validated());

        return CategoryCommentResource::make($comment)->response()->setStatusCode(201);
    }

    /**
     * Scoped to its project, so a comment id from another project is a 404 rather than a delete.
     */
    public function destroy(Category $category, CategoryComment $comment): JsonResponse
    {
        abort_unless($comment->category_id === $category->id, 404);

        $comment->delete();

        return response()->json(['message' => 'Comment deleted.']);
    }
}
