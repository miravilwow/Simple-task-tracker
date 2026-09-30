<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreTaskRequest;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Src\TaskSorter;

class TaskController extends Controller
{
    public function index(Request $request, TaskSorter $sorter): JsonResponse
    {
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(Task::STATUSES)],
        ]);

        $tasks = Task::query()
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->get();

        return response()->json(['data' => $sorter->sortTasks($tasks->toArray())]);
    }

    public function store(StoreTaskRequest $request): JsonResponse
    {
        $task = Task::create($request->validated());

        return response()->json(['data' => $task], 201);
    }

    public function complete(Task $task): JsonResponse
    {
        $task->status = Task::STATUS_COMPLETED;
        $task->save();

        return response()->json(['data' => $task]);
    }

    public function destroy(Task $task): JsonResponse
    {
        $task->delete();

        return response()->json(['message' => 'Task deleted.']);
    }
}
