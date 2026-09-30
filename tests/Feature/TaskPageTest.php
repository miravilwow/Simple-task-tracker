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

    public function test_landing_page_links_to_the_tracker(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('Stay on top of what matters most.')
            ->assertSee('href="' . route('tasks.index') . '"', false)
            ->assertSee('Open app');
    }

    public function test_tracker_page_renders_the_task_form(): void
    {
        $this->get('/tasks')
            ->assertOk()
            ->assertSee('Your tasks')
            ->assertSee('id="task-form"', false)
            ->assertSee('href="' . route('home') . '"', false)
            ->assertDontSee('Open app');
    }
}
