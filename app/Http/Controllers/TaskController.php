<?php

namespace App\Http\Controllers;

use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Resources\TaskResource;
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
            'status' => ['nullable', Rule::enum(TaskStatus::class)],
        ]);

        $tasks = Task::query()
            ->when($validated['status'] ?? null, fn ($query, $status) => $query->where('status', $status))
            ->get();

        // TaskSorter works on plain arrays, so serialize through the resource first.
        $rows = TaskResource::collection($tasks)->resolve($request);

        return response()->json(['data' => $sorter->sortTasks($rows)]);
    }

    public function stats(): JsonResponse
    {
        $pending = TaskStatus::Pending->value;

        // One query with conditional counts instead of four separate COUNT queries.
        $counts = Task::query()->toBase()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS pending', [$pending])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS completed', [TaskStatus::Completed->value])
            ->selectRaw(
                'SUM(CASE WHEN status = ? AND priority = ? THEN 1 ELSE 0 END) AS high_priority_pending',
                [$pending, TaskPriority::High->value]
            )
            ->first();

        // SUM() returns NULL on an empty table and drivers may return numeric strings, so normalise to int.
        return response()->json(['data' => array_map('intval', (array) $counts)]);
    }

    public function store(StoreTaskRequest $request): JsonResponse
    {
        $task = Task::create($request->validated());

        return TaskResource::make($task)->response()->setStatusCode(201);
    }

    public function complete(Task $task): TaskResource
    {
        $task->status = TaskStatus::Completed;
        $task->save();

        return TaskResource::make($task);
    }

    public function destroy(Task $task): JsonResponse
    {
        $task->delete();

        return response()->json(['message' => 'Task deleted.']);
    }
}
