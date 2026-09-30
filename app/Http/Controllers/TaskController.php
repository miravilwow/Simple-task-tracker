<?php

namespace App\Http\Controllers;

use App\Enums\DueFilter;
use App\Enums\TaskPriority;
use App\Enums\TaskStatus;
use App\Http\Requests\ScheduleTaskRequest;
use App\Http\Requests\StoreTaskRequest;
use App\Http\Resources\TaskResource;
use App\Models\Task;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Src\TaskSorter;

class TaskController extends Controller
{
    public function index(Request $request, TaskSorter $sorter): JsonResponse
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::enum(TaskStatus::class)],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'due' => ['nullable', Rule::enum(DueFilter::class)],
            'from' => ['nullable', 'date_format:Y-m-d'],
            'to' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:from'],
        ]);

        $tasks = Task::query()
            // Without this the list would run one category query per row.
            ->with('category')
            ->when($filters['status'] ?? null, fn (Builder $query, string $status) => $query->where('status', $status))
            ->when($filters['category_id'] ?? null, fn (Builder $query, int $id) => $query->where('category_id', $id))
            ->when($filters['due'] ?? null, fn (Builder $query, string $due) => $this->applyDueFilter($query, DueFilter::from($due)))
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->whereDate('due_date', '>=', $from))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->whereDate('due_date', '<=', $to))
            ->get();

        // TaskSorter works on plain arrays, so serialize through the resource first.
        $rows = TaskResource::collection($tasks)->resolve($request);

        return response()->json(['data' => $this->pendingFirst($sorter->sortTasks($rows))]);
    }

    public function stats(): JsonResponse
    {
        $pending = TaskStatus::Pending->value;

        // One query with conditional counts instead of six separate COUNT queries.
        $counts = Task::query()->toBase()
            ->selectRaw('COUNT(*) AS total')
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS pending', [$pending])
            ->selectRaw('SUM(CASE WHEN status = ? THEN 1 ELSE 0 END) AS completed', [TaskStatus::Completed->value])
            ->selectRaw(
                'SUM(CASE WHEN status = ? AND priority = ? THEN 1 ELSE 0 END) AS high_priority_pending',
                [$pending, TaskPriority::High->value]
            )
            // DATE() because SQLite stores the cast date with a 00:00:00 time that a bare
            // comparison against 'Y-m-d' would never match.
            ->selectRaw(
                'SUM(CASE WHEN status = ? AND DATE(due_date) < ? THEN 1 ELSE 0 END) AS overdue',
                [$pending, today()->toDateString()]
            )
            ->selectRaw(
                'SUM(CASE WHEN status = ? AND DATE(due_date) = ? THEN 1 ELSE 0 END) AS due_today',
                [$pending, today()->toDateString()]
            )
            ->first();

        // SUM() returns NULL on an empty table and drivers may return numeric strings, so normalise to int.
        return response()->json(['data' => array_map('intval', (array) $counts)]);
    }

    public function store(StoreTaskRequest $request): JsonResponse
    {
        $task = Task::create($request->validated());

        return TaskResource::make($task->load('category'))->response()->setStatusCode(201);
    }

    public function complete(Task $task): TaskResource
    {
        $task->status = TaskStatus::Completed;
        $task->save();

        return TaskResource::make($task->load('category'));
    }

    public function reopen(Task $task): TaskResource
    {
        $task->status = TaskStatus::Pending;
        $task->save();

        return TaskResource::make($task->load('category'));
    }

    /**
     * Backs dragging a task onto a day in the calendar, and clearing its date again.
     */
    public function schedule(ScheduleTaskRequest $request, Task $task): TaskResource
    {
        $task->due_date = $request->validated('due_date');
        $task->save();

        return TaskResource::make($task->load('category'));
    }

    public function destroy(Task $task): JsonResponse
    {
        $task->delete();

        return response()->json(['message' => 'Task deleted.']);
    }

    /**
     * @param  Builder<Task>  $query
     */
    private function applyDueFilter(Builder $query, DueFilter $due): void
    {
        $today = today()->toDateString();

        match ($due) {
            DueFilter::Overdue => $query->whereDate('due_date', '<', $today)->where('status', TaskStatus::Pending),
            DueFilter::Today => $query->whereDate('due_date', $today),
            DueFilter::Upcoming => $query->whereDate('due_date', '>=', $today),
        };
    }

    /**
     * TaskSorter ranks by priority then age, which would let a finished task from last week sit
     * above today's urgent one. Splitting the sorted list keeps each group in TaskSorter's order
     * while leaving the actionable work on top.
     *
     * @param  array<int, array{status: string}>  $tasks
     * @return array<int, array{status: string}>
     */
    private function pendingFirst(array $tasks): array
    {
        $isPending = fn (array $task) => $task['status'] === TaskStatus::Pending->value;

        return [
            ...array_filter($tasks, $isPending),
            ...array_filter($tasks, fn (array $task) => ! $isPending($task)),
        ];
    }
}
