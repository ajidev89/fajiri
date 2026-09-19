<?php

namespace Tests\Feature;

use Tests\TestCase;

class QueueScheduleTest extends TestCase
{
    public function test_queue_work_is_registered_on_the_scheduler(): void
    {
        $this->artisan('schedule:list')
            ->expectsOutputToContain('queue:work')
            ->assertSuccessful();
    }
}
