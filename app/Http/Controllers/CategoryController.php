<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Requests\UpdateCategoryRequest;
use App\Http\Resources\ActivityResource;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    /**
     * The feed is a glance at what happened lately, not an audit trail to page through.
     */
    private const ACTIVITY_LIMIT = 50;

    public function index(): AnonymousResourceCollection
    {
        $categories = Category::query()
            ->withCount('tasks')
            ->orderBy('name')
            ->get();

        return CategoryResource::collection($categories);
    }

    public function store(StoreCategoryRequest $request): JsonResponse
    {
        $category = Category::create($request->validated());

        return CategoryResource::make($category->loadCount('tasks'))->response()->setStatusCode(201);
    }

    public function update(UpdateCategoryRequest $request, Category $category): CategoryResource
    {
        $category->update($request->validated());

        return CategoryResource::make($category->loadCount('tasks'));
    }

    /**
     * The project's recent history, newest first.
     */
    public function activity(Category $category): AnonymousResourceCollection
    {
        $activities = $category->activities()
            // id as well as created_at, because several entries can share a second and the feed
            // would otherwise order them arbitrarily.
            ->latest('created_at')
            ->latest('id')
            ->limit(self::ACTIVITY_LIMIT)
            ->get();

        return ActivityResource::collection($activities);
    }

    public function destroy(Category $category): JsonResponse
    {
        // The foreign key is nullOnDelete, so the category's tasks survive as uncategorised.
        $category->delete();

        return response()->json(['message' => 'Category deleted.']);
    }
}
