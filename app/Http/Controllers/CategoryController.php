<?php

namespace App\Http\Controllers;

use App\Http\Resources\CategoryResource;
use App\Models\Category;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class CategoryController extends Controller
{
    /**
     * Only projects that still hold a live task. A project is created by typing its name on a
     * task and nothing deletes it, so this is what keeps an abandoned or mistyped one out of the
     * picker.
     */
    public function index(): AnonymousResourceCollection
    {
        $categories = Category::query()
            ->withCount('tasks')
            ->has('tasks')
            ->orderBy('name')
            ->get();

        return CategoryResource::collection($categories);
    }
}
