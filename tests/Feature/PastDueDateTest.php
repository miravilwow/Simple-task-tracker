<?php

namespace Tests\Feature;

use App\Models\Task;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * A due date is a promise about work still ahead, so it cannot be set in the past. A task still
 * becomes overdue the ordinary way: the day arrives and passes.
 */
class PastDueDateTest extends TestCase
{
    use RefreshDatabase;

    private function create(array $overrides = []): \Illuminate\Testing\TestResponse
    {
        return $this->postJson('/api/tasks', [
            'title' => 'Ship it',
            'priority' => 'medium',
            ...$overrides,
        ]);
    }

    public function test_rejects_a_task_created_with_a_past_due_date(): void
    {
        $this->create(['due_date' => today()->subDay()->toDateString()])
            ->assertStatus(400)
            ->assertJsonValidationErrors('due_date');
    }

    public function test_accepts_a_task_due_today(): void
    {
        $this->create(['due_date' => today()->toDateString()])->assertCreated();
    }

    public function test_accepts_a_task_due_later(): void
    {
        $this->create(['due_date' => today()->addMonth()->toDateString()])->assertCreated();
    }

    public function test_rejects_a_task_created_with_no_due_date(): void
    {
        $this->create()->assertStatus(400)->assertJsonValidationErrors('due_date');
    }

    public function test_rejects_rescheduling_into_the_past(): void
    {
        // Without this, creating a task with no date and dragging it onto a past day would be
        // the way around the rule.
        $task = Task::factory()->create();

        $this->patchJson("/api/tasks/{$task->id}/schedule", ['due_date' => today()->subWeek()->toDateString()])
            ->assertStatus(400)
            ->assertJsonValidationErrors('due_date');
    }

    public function test_still_clears_a_due_date(): void
    {
        $task = Task::factory()->create(['due_date' => today()->addDay()]);

        $this->patchJson("/api/tasks/{$task->id}/schedule", ['due_date' => null])
            ->assertOk()
            ->assertJsonPath('data.due_date', null);
    }

    public function test_a_task_that_is_already_overdue_can_be_moved_forward(): void
    {
        // Factories write the model directly, so yesterday's rows still exist and still need a
        // way out.
        $task = Task::factory()->create(['due_date' => today()->subWeek()]);

        $this->patchJson("/api/tasks/{$task->id}/schedule", ['due_date' => today()->toDateString()])
            ->assertOk()
            ->assertJsonPath('data.is_overdue', false);
    }

    /** The form must not offer a submit the server refuses, so the field is marked and required. */
    public function test_the_new_task_form_requires_a_due_date(): void
    {
        $html = $this->get('/tasks')->assertOk()->getContent();
        $field = Str::before(Str::after($html, 'id="due_date"'), '>');

        $this->assertStringContainsString('required', $field);
    }

    public function test_the_date_fields_cannot_offer_what_the_api_refuses(): void
    {
        $html = $this->get('/tasks')->assertOk()->getContent();

        $this->assertSame(
            substr_count($html, 'type="date"'),
            substr_count($html, 'min="' . today()->toDateString() . '"'),
            'A date input is missing the min attribute the server enforces'
        );
    }
}
