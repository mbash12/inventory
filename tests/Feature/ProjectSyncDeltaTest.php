<?php

namespace Tests\Feature;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Src\Models\AccountingSyncSnapshot;
use Src\Models\PoDeposit;
use Src\Models\Product;
use Src\Models\Project;
use Src\Services\ProjectSyncService;

class ProjectSyncDeltaTest extends TestCase
{
    private ProjectSyncService $service;

    protected function setUp(): void
    {
        parent::setUp();

        $app = new Container;
        Container::setInstance($app);
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication($app);

        $app->instance('config', new Repository([
            'app' => ['accounting_api_url' => 'http://accounting.test/api'],
            'database' => ['default' => 'default'],
        ]));

        $capsule = new Capsule($app);
        $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
        \Illuminate\Database\Eloquent\Model::clearBootedModels();
        $app->instance('db', $capsule->getDatabaseManager());
        $app->instance('db.schema', $capsule->getConnection()->getSchemaBuilder());
        $app->instance('log', new NullLogger);
        $app->instance(HttpFactory::class, new HttpFactory);

        Schema::create('po_deposits', function (Blueprint $table) {
            $table->id();
            $table->string('job_number');
            $table->date('client_po_date')->nullable();
            $table->string('client_po_number')->nullable();
            $table->string('client_company')->nullable();
            $table->string('client_code')->nullable();
            $table->boolean('is_po_deposit')->default(false);
            $table->boolean('is_bundle')->default(false);
            $table->string('ppn_type')->nullable();
            $table->timestamps();
        });
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->integer('po_deposit')->nullable();
            $table->string('job_number')->nullable();
            $table->date('client_po_date')->nullable();
            $table->string('client_po_number')->nullable();
            $table->boolean('is_real')->default(true);
            $table->integer('deposit_id')->nullable();
            $table->string('project_type')->nullable();
            $table->timestamps();
        });
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->integer('project');
            $table->string('product_code')->nullable();
            $table->string('name')->nullable();
            $table->text('description')->nullable();
            $table->decimal('quantity', 15, 2)->default(1);
            $table->decimal('price', 15, 2)->default(0);
            $table->string('uom_code')->nullable();
            $table->string('tax_code')->nullable();
            $table->boolean('is_production')->default(false);
            $table->decimal('qty_per_set', 15, 2)->nullable();
            $table->boolean('is_group_main')->default(false);
            $table->timestamps();
        });
        (require __DIR__ . '/../../src/migrations/2026_10_09_000001_create_accounting_sync_snapshots_table.php')->up();

        Http::fake(function (Request $request) {
            $next = array_shift($this->replies) ?? ['body' => ['data' => ['sales_order_id' => 500, 'order_number' => 'SO-500', 'sync_version' => 0]]];

            return Http::response($next['body'] ?? [], $next['status'] ?? 200);
        });

        $this->service = new ProjectSyncService;
    }

    protected function tearDown(): void
    {
        Facade::clearResolvedInstances();
        Facade::setFacadeApplication(null);
        Container::setInstance(null);
        parent::tearDown();
    }

    private function poDeposit(bool $deposit = false): PoDeposit
    {
        return PoDeposit::create([
            'job_number' => 'JOB-1', 'client_po_date' => '2026-10-01', 'client_po_number' => 'PO-1',
            'client_company' => 'PT Maju', 'client_code' => 'C1', 'is_po_deposit' => $deposit, 'ppn_type' => 'ppn',
        ]);
    }

    private function project(PoDeposit $po, array $attrs = []): Project
    {
        return Project::create($attrs + [
            'po_deposit' => $po->id, 'job_number' => 'JOB-1', 'client_po_number' => 'PO-1', 'is_real' => true, 'project_type' => 'gimmick',
        ]);
    }

    private function product(Project $project, string $code, float $qty = 10, float $price = 100): Product
    {
        return Product::create([
            'project' => $project->id, 'product_code' => $code, 'name' => "Name {$code}", 'description' => '',
            'quantity' => $qty, 'price' => $price, 'uom_code' => 'PCS', 'tax_code' => 'PPN', 'is_production' => true,
        ]);
    }

    /** @var list<array{status?: int, body?: array}> */
    private array $replies = [];

    private int $mark = 0;

    /** Queue the next Accounting replies (default: 200 OK) and start counting sent requests from here. */
    private function accountingReplies(array ...$responses): void
    {
        $this->replies = $responses;
        $this->mark = count(Http::recorded());
    }

    /** Request bodies sent since the last accountingReplies() call. */
    private function sent(): array
    {
        $all = array_map(fn ($pair) => $pair[0]->data(), Http::recorded()->all());

        return array_values(array_slice($all, $this->mark));
    }

    private function sync(PoDeposit $po, bool $full = false): array
    {
        return $this->service->syncSinglePoDeposit($po->fresh(), 12, $full);
    }

    public function test_first_sync_sends_full_payload_and_stores_snapshot(): void
    {
        $po = $this->poDeposit();
        $this->product($this->project($po), 'A');
        $this->accountingReplies();

        $result = $this->sync($po);

        $this->assertSame([], $result['errors']);
        $body = $this->sent()[0];
        $this->assertArrayNotHasKey('mode', $body);
        $this->assertSame($po->id, $body['source_po_deposit_id']);
        $this->assertSame(1, $body['sync_version']);

        $snapshot = AccountingSyncSnapshot::first();
        $this->assertSame('po', $snapshot->so_key);
        $this->assertSame(1, $snapshot->sync_version);
        $this->assertSame(500, $snapshot->accounting_so_id);
        $this->assertArrayHasKey(1, $snapshot->state['items']);
    }

    public function test_unchanged_po_sends_nothing(): void
    {
        $po = $this->poDeposit();
        $this->product($this->project($po), 'A');
        $this->accountingReplies();
        $this->sync($po);

        $result = $this->sync($po);

        $this->assertSame([], $result['errors']);
        $this->assertCount(1, $this->sent());
    }

    public function test_quantity_edit_sends_only_that_field(): void
    {
        $po = $this->poDeposit();
        $a = $this->product($this->project($po), 'A');
        $this->product(Project::first(), 'B');
        $this->accountingReplies();
        $this->sync($po);

        $a->update(['quantity' => 12]);
        $this->accountingReplies(['body' => ['data' => ['sales_order_id' => 500, 'order_number' => 'SO-500', 'status' => 'applied', 'sync_version' => 2]]]);
        $this->sync($po);

        $delta = $this->sent()[0];
        $this->assertSame('delta', $delta['mode']);
        $this->assertSame(2, $delta['sync_version']);
        $this->assertSame($po->id, $delta['source_po_deposit_id']);
        $this->assertSame([], $delta['header']);
        $this->assertEquals([['op' => 'update', 'source_product_id' => $a->id, 'changes' => ['quantity' => 12]]], $delta['items']);
        $this->assertSame(2, AccountingSyncSnapshot::first()->sync_version);
        $this->assertEquals(12, AccountingSyncSnapshot::first()->state['items'][$a->id]['quantity']);
    }

    public function test_added_and_removed_products_become_create_and_delete(): void
    {
        $po = $this->poDeposit();
        $project = $this->project($po);
        $a = $this->product($project, 'A');
        $b = $this->product($project, 'B');
        $this->accountingReplies();
        $this->sync($po);

        $b->delete();
        $c = $this->product($project, 'C');
        $this->accountingReplies();
        $this->sync($po);

        $ops = $this->sent()[0]['items'];
        $this->assertSame(['create', 'delete'], array_column($ops, 'op'));
        $this->assertSame([$c->id, $b->id], array_column($ops, 'source_product_id'));
        $this->assertSame('C', $ops[0]['data']['product_code']);
        $this->assertSame([$a->id, $c->id], array_keys(AccountingSyncSnapshot::first()->state['items']));
    }

    public function test_unknown_order_falls_back_to_full_sync(): void
    {
        $po = $this->poDeposit();
        $a = $this->product($this->project($po), 'A');
        $this->accountingReplies();
        $this->sync($po);

        $a->update(['quantity' => 12]);
        $this->accountingReplies(
            ['status' => 404, 'body' => ['reasons' => [['code' => 'not_found']]]],
            ['body' => ['data' => ['sales_order_id' => 501, 'order_number' => 'SO-501']]],
        );
        $result = $this->sync($po);

        $this->assertSame([], $result['errors']);
        [$first, $second] = $this->sent();
        $this->assertSame('delta', $first['mode']);
        $this->assertArrayNotHasKey('mode', $second);
        $this->assertSame(2, $second['sync_version']);
        $this->assertSame(501, AccountingSyncSnapshot::first()->accounting_so_id);
    }

    public function test_unmapped_item_falls_back_to_full_sync(): void
    {
        $po = $this->poDeposit();
        $a = $this->product($this->project($po), 'A');
        $this->accountingReplies();
        $this->sync($po);

        $a->update(['quantity' => 12]);
        $this->accountingReplies(
            ['status' => 422, 'body' => ['reasons' => [['code' => 'needs_full_sync']]]],
            [],
        );

        $this->assertSame([], $this->sync($po)['errors']);
        $this->assertCount(2, $this->sent());
    }

    public function test_stale_delta_falls_back_to_full_with_a_higher_version(): void
    {
        $po = $this->poDeposit();
        $a = $this->product($this->project($po), 'A');
        $this->accountingReplies();
        $this->sync($po);

        $a->update(['quantity' => 12]);
        $this->accountingReplies(
            ['body' => ['data' => ['status' => 'stale', 'sync_version' => 7]]],
            [],
        );
        $this->sync($po);

        $this->assertSame(8, $this->sent()[1]['sync_version']);
        $this->assertSame(8, AccountingSyncSnapshot::first()->sync_version);
    }

    public function test_rejection_blocks_and_keeps_the_snapshot(): void
    {
        $po = $this->poDeposit();
        $a = $this->product($this->project($po), 'A');
        $this->accountingReplies();
        $this->sync($po);

        $a->update(['quantity' => 1]);
        $this->accountingReplies(['status' => 409, 'body' => ['reasons' => [['code' => 'qty_below_delivered', 'detail' => 'quantity 1 is below the 8 already delivered']]]]);
        $result = $this->sync($po);

        $this->assertTrue($result['blocked']);
        $this->assertSame('qty_below_delivered', $result['errors'][0]['reasons'][0]['code']);
        $snapshot = AccountingSyncSnapshot::first();
        $this->assertSame(1, $snapshot->sync_version);
        $this->assertEquals(10, $snapshot->state['items'][$a->id]['quantity']);

        // Once the blocking edit is reverted there is nothing left to send.
        $a->update(['quantity' => 10]);
        $this->accountingReplies();
        $this->assertSame([], $this->sync($po)['errors']);
        $this->assertCount(0, $this->sent());
    }

    public function test_force_full_ignores_the_snapshot(): void
    {
        $po = $this->poDeposit();
        $this->product($this->project($po), 'A');
        $this->accountingReplies();
        $this->sync($po);

        $this->accountingReplies();
        $this->sync($po, true);

        $body = $this->sent()[0];
        $this->assertArrayNotHasKey('mode', $body);
        $this->assertSame(2, $body['sync_version']);
    }

    public function test_deposit_projects_are_tracked_per_project(): void
    {
        $po = $this->poDeposit(true);
        $deposit = $this->project($po, ['is_real' => false, 'job_number' => 'JOB-1-D']);
        $actual = $this->project($po, ['is_real' => true, 'deposit_id' => $deposit->id, 'job_number' => 'JOB-1-A']);
        $this->product($deposit, 'A');
        $a2 = $this->product($actual, 'B');
        $this->accountingReplies();
        $this->sync($po);

        $this->assertEqualsCanonicalizing(['p' . $deposit->id, 'p' . $actual->id], AccountingSyncSnapshot::pluck('so_key')->all());

        $a2->update(['quantity' => 3]);
        $this->accountingReplies();
        $this->sync($po);

        $this->assertCount(1, $this->sent());
        $delta = $this->sent()[0];
        $this->assertSame($actual->id, $delta['source_project_id']);
        $this->assertSame('JOB-1-A', $delta['lookup_job_number']);
        $this->assertEquals(3, $delta['items'][0]['changes']['quantity']);
    }

    public function test_a_removed_project_has_its_items_retired(): void
    {
        $po = $this->poDeposit(true);
        $deposit = $this->project($po, ['is_real' => false, 'job_number' => 'JOB-1-D']);
        $actual = $this->project($po, ['is_real' => true, 'deposit_id' => $deposit->id, 'job_number' => 'JOB-1-A']);
        $this->product($deposit, 'A');
        $b = $this->product($actual, 'B');
        $this->accountingReplies();
        $this->sync($po);

        $actual->delete();
        $this->accountingReplies();
        $this->sync($po);

        $bodies = $this->sent();
        $retire = collect($bodies)->firstWhere('source_project_id', $actual->id);
        $this->assertSame([['op' => 'delete', 'source_product_id' => $b->id]], $retire['items']);
        $this->assertSame([], AccountingSyncSnapshot::where('so_key', 'p' . $actual->id)->first()->state['items']);
    }
}
