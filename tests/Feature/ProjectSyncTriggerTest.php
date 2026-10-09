<?php

namespace Tests\Feature;

use Illuminate\Bus\Dispatcher;
use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Contracts\Bus\Dispatcher as DispatcherContract;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Testing\Fakes\BusFake;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Src\Jobs\ProjectSyncJob;
use Src\Models\PoDeposit;
use Src\Services\ProjectSyncTrigger;
use Src\Services\SyncLockService;

class ProjectSyncTriggerTest extends TestCase
{
    private BusFake $bus;

    protected function setUp(): void
    {
        parent::setUp();

        $app = new Container;
        Container::setInstance($app);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($app);

        $this->configure(['disabled' => false, 'on_update' => false]);
        $app->instance('config', $this->config);

        $capsule = new Capsule($app);
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
        \Illuminate\Database\Eloquent\Model::clearBootedModels();
        $app->instance('db', $capsule->getDatabaseManager());
        $app->instance('db.schema', $capsule->getConnection()->getSchemaBuilder());
        $app->instance('log', new NullLogger);

        // PendingDispatch takes the unique-job lock from the cache.
        $app->instance(\Illuminate\Contracts\Cache\Repository::class, new \Illuminate\Cache\Repository(new \Illuminate\Cache\ArrayStore));

        $this->bus = new BusFake(new Dispatcher($app));
        $app->instance(DispatcherContract::class, $this->bus);

        Schema::create('po_deposits', function (Blueprint $table) {
            $table->id();
            $table->string('job_number')->nullable();
            $table->string('client_code')->nullable();
            $table->string('ppn_type')->nullable();
            $table->string('sync_status')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();
        });
    }

    protected function tearDown(): void
    {
        SyncLockService::unlockPoDeposit(1);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Container::setInstance(null);
        parent::tearDown();
    }

    private Repository $config;

    private function configure(array $overrides): void
    {
        $this->config = new Repository([
            'queue' => ['default' => 'database'],
            'project_sync' => $overrides + [
                'delay_seconds' => 5, 'queue' => 'default', 'ppn_company_id' => 12, 'non_ppn_company_id' => 1,
            ],
            'database' => ['default' => 'default'],
        ]);
        Container::getInstance()->instance('config', $this->config);
    }

    private function po(array $attrs = []): PoDeposit
    {
        $po = new PoDeposit;
        $po->forceFill($attrs + ['job_number' => 'JOB-1', 'client_code' => 'C1', 'ppn_type' => 'ppn'])->save();

        return $po;
    }

    public function test_never_synced_po_is_dispatched_with_its_company_and_marked_pending(): void
    {
        $po = $this->po(['ppn_type' => 'non_ppn']);

        ProjectSyncTrigger::request($po, 'test');

        $this->bus->assertDispatchedTimes(ProjectSyncJob::class, 1);
        $this->bus->assertDispatched(ProjectSyncJob::class, function (ProjectSyncJob $job) use ($po) {
            return $job->uniqueId() === 'project-sync:' . $po->id && $job->delay !== null;
        });
        $this->assertSame('pending', $po->fresh()->sync_status);
        $this->assertSame(1, ProjectSyncTrigger::companyIdFor($po));
    }

    public function test_synced_po_is_ignored_while_on_update_is_off(): void
    {
        $po = $this->po(['last_synced_at' => now()]);

        ProjectSyncTrigger::request($po, 'test');

        $this->bus->assertNothingDispatched();
        $this->assertNull($po->fresh()->sync_status);
    }

    public function test_synced_po_is_dispatched_once_on_update_is_on(): void
    {
        $this->configure(['disabled' => false, 'on_update' => true]);
        $po = $this->po(['last_synced_at' => now()]);

        ProjectSyncTrigger::request($po, 'test');

        $this->bus->assertDispatchedTimes(ProjectSyncJob::class, 1);
        $this->assertNotNull($po->fresh()->last_synced_at, 'last_synced_at must not be wiped');
    }

    public function test_a_burst_of_edits_queues_a_single_job(): void
    {
        $po = $this->po();

        ProjectSyncTrigger::request($po, 'project updated');
        ProjectSyncTrigger::request($po->fresh(), 'product updated');
        ProjectSyncTrigger::request($po->fresh(), 'product created');

        $this->bus->assertDispatchedTimes(ProjectSyncJob::class, 1);
    }

    public function test_kill_switch_blocks_everything(): void
    {
        $this->configure(['disabled' => true, 'on_update' => true]);

        ProjectSyncTrigger::request($this->po(), 'test');

        $this->bus->assertNothingDispatched();
    }

    public function test_po_without_client_code_is_skipped(): void
    {
        ProjectSyncTrigger::request($this->po(['client_code' => null]), 'test');

        $this->bus->assertNothingDispatched();
    }

    public function test_back_sync_lock_prevents_the_echo(): void
    {
        $po = $this->po();
        SyncLockService::lockPoDeposit($po->id);

        ProjectSyncTrigger::request($po, 'test');

        $this->bus->assertNothingDispatched();
    }

    public function test_manual_runs_do_not_share_the_automatic_unique_key(): void
    {
        $this->assertNotSame(
            (new ProjectSyncJob(7))->uniqueId(),
            (new ProjectSyncJob(7, 12, 99))->uniqueId(),
        );
        $this->assertNotSame(
            (new ProjectSyncJob(7))->uniqueId(),
            (new ProjectSyncJob(7, 12, null, true))->uniqueId(),
        );
    }
}
