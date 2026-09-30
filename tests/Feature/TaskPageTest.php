<?php

namespace Tests\Feature;

use Tests\TestCase;

class TaskPageTest extends TestCase
{
    public function test_home_page_renders_the_task_tracker(): void
    {
        $this->withoutVite();

        $this->get('/')
            ->assertOk()
            ->assertSee('Simple Task Tracker')
            ->assertSee('id="task-form"', false);
    }
}
