<?php

namespace Tests\Unit\Modules\AttendanceIntegration\Services;

use Modules\AttendanceIntegration\Services\LivePunchFeedService;
use Tests\TestCase;

class LivePunchFeedServiceTest extends TestCase
{
    private LivePunchFeedService $service;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app['config']->set('cache.default', 'array');

        $this->service = new LivePunchFeedService;
    }

    public function test_get_recent_punches_empty_by_default(): void
    {
        $punches = $this->service->getRecentPunches();
        $this->assertEmpty($punches);
    }

    public function test_add_punch_and_retrieve(): void
    {
        $this->service->addPunch(['user' => ['name' => 'Ahmed'], 'punch_type' => 'check_in']);
        $this->service->addPunch(['user' => ['name' => 'Sara'], 'punch_type' => 'check_out']);

        $punches = $this->service->getRecentPunches();

        $this->assertCount(2, $punches);
        $this->assertSame('Sara', $punches[0]['user']['name']);
        $this->assertSame('Ahmed', $punches[1]['user']['name']);
    }

    public function test_add_punch_respects_limit(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->service->addPunch(['index' => $i]);
        }

        $punches = $this->service->getRecentPunches(5);
        $this->assertCount(5, $punches);
        $this->assertSame(9, $punches[0]['index']);
        $this->assertSame(5, $punches[4]['index']);
    }

    public function test_clear_feed(): void
    {
        $this->service->addPunch(['test' => true]);
        $this->assertNotEmpty($this->service->getRecentPunches());

        $this->service->clearFeed();
        $this->assertEmpty($this->service->getRecentPunches());
    }

    public function test_get_recent_punches_respects_limit_parameter(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->service->addPunch(['index' => $i]);
        }

        $punches = $this->service->getRecentPunches(2);
        $this->assertCount(2, $punches);
    }
}
