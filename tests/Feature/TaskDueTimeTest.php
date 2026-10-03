<?php

namespace Tests\Feature;

use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The time of day a task is due. It is extra detail on a task and nothing more: the day is still
 * what decides whether a task is overdue, which view it appears in and where it sits in the
 * calendar, so none of those is allowed to notice this field.
 */
class TaskDueTimeTest extends TestCase
{
    use RefreshDatabase;

    private function create(array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/tasks', [
            'title' => 'Stand-up',
            'priority' => 'medium',
            'due_date' => today()->toDateString(),
            ...$overrides,
        ]);
    }

    public function test_creates_a_task_with_a_time(): void
    {
        $this->create(['due_time' => '10:30'])
            ->assertCreated()
            ->assertJsonPath('data.due_time', '10:30');
    }

    public function test_a_time_is_required_on_create(): void
    {
        $this->create()
            ->assertStatus(400)
            ->assertJsonValidationErrors('due_time');
    }

    public function test_rejects_a_time_that_is_not_hours_and_minutes(): void
    {
        $this->create(['due_time' => '10:30 AM'])
            ->assertStatus(400)
            ->assertJsonValidationErrors('due_time');
    }

    public function test_the_dialog_can_set_and_clear_the_time(): void
    {
        $task = Task::factory()->create(['due_date' => today(), 'due_time' => null]);

        $this->patchJson("/api/tasks/{$task->id}", ['due_time' => '09:15'])
            ->assertOk()
            ->assertJsonPath('data.due_time', '09:15');

        $this->patchJson("/api/tasks/{$task->id}", ['due_time' => null])
            ->assertOk()
            ->assertJsonPath('data.due_time', null);
    }

    /** A time with no day to sit on means nothing, so taking the day off takes it with it. */
    public function test_clearing_the_date_clears_the_time(): void
    {
        $task = Task::factory()->create(['due_date' => today(), 'due_time' => '10:30']);

        $this->patchJson("/api/tasks/{$task->id}/schedule", ['due_date' => null])
            ->assertOk()
            ->assertJsonPath('data.due_date', null)
            ->assertJsonPath('data.due_time', null);
    }

    /** Dragging a card onto another day moves the day; the time it was due at is not a guess. */
    public function test_rescheduling_keeps_the_time(): void
    {
        $task = Task::factory()->create(['due_date' => today(), 'due_time' => '10:30']);

        $this->patchJson("/api/tasks/{$task->id}/schedule", ['due_date' => today()->addDay()->toDateString()])
            ->assertOk()
            ->assertJsonPath('data.due_time', '10:30');
    }

    /** A silent no-op is what the model's own guard would make of it, so the API refuses first. */
    public function test_refuses_a_time_on_a_task_with_no_date(): void
    {
        $task = Task::factory()->create(['due_date' => null]);

        $this->patchJson("/api/tasks/{$task->id}", ['due_time' => '10:30'])
            ->assertStatus(400)
            ->assertJsonValidationErrors('due_time');
    }

    public function test_both_dialogs_offer_a_time_field(): void
    {
        $this->get('/tasks')->assertOk()->assertSeeInOrder([
            'id="due_time"',
            'id="detail-time"',
        ], false);
    }

    /**
     * The grid is the tallest thing on the New task form, so it waits behind a trigger. It is
     * still the in-flow grid, never a floating panel, which a <dialog> would clip.
     */
    public function test_the_new_task_date_is_a_collapsed_grid(): void
    {
        $html = $this->get('/tasks')->assertOk()->getContent();

        $this->assertStringContainsString('data-date-field="collapsed"', $html);
        $this->assertStringContainsString('aria-controls="due_date-grid"', $html);
    }

    /** The day decides lateness, so a time earlier than now on today's date is not overdue. */
    public function test_a_time_earlier_today_is_not_overdue(): void
    {
        $task = Task::factory()->create(['due_date' => today(), 'due_time' => '00:01']);

        $this->getJson("/api/tasks/{$task->id}")
            ->assertOk()
            ->assertJsonPath('data.is_overdue', false);
    }
}
