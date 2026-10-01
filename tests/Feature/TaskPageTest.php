<?php

namespace Tests\Feature;

use Tests\TestCase;

class TaskPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
    }

    public function test_the_root_leads_to_the_tracker(): void
    {
        // The tracker is the whole site now. `/` redirects rather than 404s, so an old link lands.
        $this->get('/')->assertRedirect(route('tasks.index'));
    }

    public function test_tracker_page_renders_the_task_form(): void
    {
        $this->get('/tasks')
            ->assertOk()
            ->assertSee('Your tasks')
            ->assertSee('id="task-form"', false);
    }
}
