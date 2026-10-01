<?php

namespace App\Http\Controllers;

use App\Http\Resources\SubtaskResource;
use App\Models\Subtask;
use App\Models\Task;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SubtaskController extends Controller
{
    public function store(Request $request, Task $task): JsonResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:255'],
        ]);

        // Appended, so a checklist keeps the order it was written in.
        $subtask = $task->subtasks()->create([
            ...$data,
            'position' => (int) $task->subtasks()->max('position') + 1,
        ]);

        return SubtaskResource::make($subtask)->response()->setStatusCode(201);
    }

    public function update(Request $request, Task $task, Subtask $subtask): SubtaskResource
    {
        $data = $request->validate([
            'title' => ['sometimes', 'required', 'string', 'max:255'],
            // Set, never toggled, so two clicks racing each other cannot undo one another.
            'is_done' => ['sometimes', 'required', 'boolean'],
        ]);

        $subtask->update($data);

        return SubtaskResource::make($subtask);
    }

    public function destroy(Task $task, Subtask $subtask): JsonResponse
    {
        // A checklist item is small enough to retype, so this one is not soft deleted.
        $subtask->delete();

        return response()->json(['message' => 'Sub-task deleted.']);
    }
}
