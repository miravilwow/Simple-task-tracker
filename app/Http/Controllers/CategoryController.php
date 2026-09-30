<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCategoryRequest;
use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
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

    public function destroy(Category $category): JsonResponse
    {
        // The foreign key is nullOnDelete, so the category's tasks survive as uncategorised.
        $category->delete();

        return response()->json(['message' => 'Category deleted.']);
    }
}
