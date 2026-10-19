<?php

namespace Tests\Feature\Frontend;

use App\Contracts\AnalysisStarter;
use App\Contracts\AuditStarter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

abstract class FrontendTestCase extends TestCase
{
    use RefreshDatabase;

    protected FakeAnalysisStarter $starter;

    protected FakeAuditStarter $auditStarter;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        $this->starter = new FakeAnalysisStarter;
        $this->app->instance(AnalysisStarter::class, $this->starter);
        $this->auditStarter = new FakeAuditStarter;
        $this->app->instance(AuditStarter::class, $this->auditStarter);
    }
}
