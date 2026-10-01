<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * The tracker page renders its own controls. A Blade typo only shows up when someone opens the
 * page, which is the one check nothing else in the suite performs.
 */
class TrackerPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_tracker_renders_the_display_panel_and_every_layout(): void
    {
        $response = $this->get('/tasks');

        $response->assertOk()->assertSeeInOrder([
            'id="display-trigger"',
            'id="display-panel"',
            'data-mode="list"',
            'data-mode="board"',
            'data-mode="calendar"',
            'id="show-completed"',
            'id="grouping"',
            'id="sorting"',
            'id="filter-date"',
            'id="filter-priority"',
            'id="display-reset"',
        ], false);
    }

    public function test_the_tracker_renders_the_three_layout_containers(): void
    {
        $this->get('/tasks')->assertOk()->assertSeeInOrder([
            'id="list-view"',
            'id="board-view"',
            'id="calendar-view"',
        ], false);
    }

    public function test_the_tracker_renders_the_task_dialog(): void
    {
        $this->get('/tasks')->assertOk()->assertSeeInOrder([
            'id="task-detail"',
            'id="detail-title"',
            'id="detail-description"',
            'id="subtask-list"',
            'id="subtask-form"',
            'id="detail-project"',
            'id="detail-date"',
            'id="detail-priority"',
        ], false);
    }

    public function test_the_old_status_filter_is_gone(): void
    {
        // The Display panel carries it now, and two controls for one thing drift apart.
        $this->get('/tasks')->assertOk()->assertDontSee('data-filter=', false);
    }
}
