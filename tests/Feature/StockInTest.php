<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\TestCase;
use Src\Models\Inventory;
use Src\Models\ManualInventory;
use Src\Models\ManualItem;
use Src\Models\Product;
use Src\Models\Project;
use Src\Models\StockIn;
use Src\Models\Warehouse;
use Src\Services\InventoryService;
use Src\Services\StockInService;

class StockInTest extends TestCase
{
    private StockInService $service;

    private InventoryService $inventory;

    protected function setUp(): void
    {
        parent::setUp();
        $app = new \Illuminate\Container\Container();
        \Illuminate\Container\Container::setInstance($app);
        \Illuminate\Support\Facades\Facade::clearResolvedInstances();
        \Illuminate\Support\Facades\Facade::setFacadeApplication($app);
        $capsule = new \Illuminate\Database\Capsule\Manager($app);
        $capsule->addConnection([
            'driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '', 'foreign_key_constraints' => true,
        ]);
        $capsule->setEventDispatcher(new \Illuminate\Events\Dispatcher($app));
        $capsule->setAsGlobal();
        $capsule->bootEloquent();
        \Illuminate\Database\Eloquent\Model::clearBootedModels();
        $app->instance('db', $capsule->getDatabaseManager());
        $app->instance('db.schema', $capsule->getConnection()->getSchemaBuilder());
        $translator = new \Illuminate\Translation\Translator(new \Illuminate\Translation\ArrayLoader(), 'en');
        $validator = new \Illuminate\Validation\Factory($translator, $app);
        $validator->setPresenceVerifier(new \Illuminate\Validation\DatabasePresenceVerifier($capsule->getDatabaseManager()));
        $app->instance('validator', $validator);
        $responses = $this->createMock(\Illuminate\Contracts\Routing\ResponseFactory::class);
        $responses->method('json')->willReturnCallback(fn ($data, $status = 200, $headers = [], $options = 0) => new \Illuminate\Http\JsonResponse($data, $status, $headers, $options));
        $app->instance(\Illuminate\Contracts\Routing\ResponseFactory::class, $responses);
        \Illuminate\Http\Request::macro('validate', function (array $rules) {
            return \Illuminate\Support\Facades\Validator::make($this->all(), $rules)->validate();
        });
        Schema::create('users', fn (Blueprint $table) => $table->id());
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->string('job_number')->nullable();
            $table->string('client_po_number')->nullable();
            $table->boolean('is_real')->default(true);
            $table->integer('manufacture')->default(3);
            $table->string('status')->default('new');
            $table->timestamps();
        });
        Schema::create('products', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project')->constrained('projects');
            $table->string('name');
            $table->integer('quantity');
            $table->timestamps();
        });
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->boolean('storage')->default(true);
            $table->timestamps();
            $table->softDeletes();
        });
        Schema::create('deliveries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('project')->constrained('projects');
            $table->integer('default_origin');
            $table->integer('destination');
            $table->integer('shipping_vendor')->nullable();
            $table->date('delivery_date');
            $table->string('do_number');
            $table->text('do_files')->nullable();
            $table->text('receipt_files')->nullable();
            $table->string('status')->default('ready');
            $table->timestamps();
        });
        Schema::create('delivery_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('delivery')->constrained('deliveries');
            $table->integer('product');
            $table->integer('origin');
            $table->integer('destination');
            $table->integer('quantity');
            $table->integer('actual_quantity');
            $table->timestamp('delivered_at')->nullable();
            $table->timestamps();
        });
        (require dirname(__DIR__, 2) . '/src/migrations/_create_inventories_table.php')->up();
        (require dirname(__DIR__, 2) . '/src/migrations/2026_10_06_000001_create_stock_ins.php')->up();
        foreach ([1 => ['Manufacture', false], 2 => ['Client', false], 3 => ['Pelangi', true], 4 => ['Chika', true]] as $id => [$name, $storage]) {
            Warehouse::create(compact('id', 'name', 'storage'));
        }
        $this->inventory = new InventoryService();
        $this->service = new StockInService($this->inventory);
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        parent::tearDown();
    }

    private function project(int $quantity = 100, bool $stockIn = true, string $job = 'JOB-1'): array
    {
        $project = Project::create(['job_number' => $job, 'stock_in_required' => $stockIn]);
        $product = Product::create(['project' => $project->id, 'name' => 'Kursi ' . $job, 'quantity' => $quantity]);
        return [$project, $product];
    }

    private function payload(?Project $project, array $items, array $extra = []): array
    {
        return array_merge([
            'project' => $project?->id, 'do_number' => 'SJ-1', 'document_date' => '2026-10-06', 'warehouse' => 3, 'items' => $items,
        ], $extra);
    }

    private function stock(Product $product, int $warehouse = 3): int
    {
        return (int) Inventory::where('product', $product->id)->where('warehouse', $warehouse)->sum('quantity');
    }

    private function assertRejected(callable $callback, string $field): void
    {
        try {
            $callback();
            $this->fail('Expected ValidationException on ' . $field);
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($field, $e->errors());
        }
    }

    public function test_saving_stock_in_adds_inventory_immediately(): void
    {
        [$project, $product] = $this->project();
        $stockIn = $this->service->save($this->payload($project, [['product' => $product->id, 'quantity' => 40]]), 1);
        $this->assertSame('received', $stockIn->status);
        $this->assertSame(40, $this->stock($product));
        $this->assertSame(40, (int) $stockIn->items->first()->actual_quantity);
        $this->assertSame('2026-10-06', $stockIn->items->first()->received_at->toDateString());
    }

    public function test_second_arrival_adds_to_stock_and_total_cannot_exceed_project_quantity(): void
    {
        [$project, $product] = $this->project(100);
        $this->service->save($this->payload($project, [['product' => $product->id, 'quantity' => 70]], ['do_number' => 'SJ-A']), 1);
        $this->service->save($this->payload($project, [['product' => $product->id, 'quantity' => 20]], ['do_number' => 'SJ-B']), 1);
        $this->assertSame(90, $this->stock($product));
        $this->assertRejected(fn () => $this->service->save($this->payload($project, [['product' => $product->id, 'quantity' => 11]], ['do_number' => 'SJ-C']), 1), 'items');
        $this->assertSame(90, $this->stock($product));
        $this->assertSame(2, StockIn::count());
    }

    public function test_edit_replaces_stock_effect_and_delete_reverts_it(): void
    {
        [$project, $product] = $this->project();
        $stockIn = $this->service->save($this->payload($project, [['product' => $product->id, 'quantity' => 60]]), 1);
        $stockIn = $this->service->save($this->payload($project, [['product' => $product->id, 'quantity' => 20]], ['do_number' => 'SJ-9']), 1, $stockIn->id);
        $this->assertSame('SJ-9', $stockIn->do_number);
        $this->assertSame(20, $this->stock($product));
        $this->assertSame(1, $stockIn->items->count());
        $this->service->destroy($stockIn->id);
        $this->assertSame(0, StockIn::count());
        $this->assertSame(0, $this->stock($product));
    }

    public function test_edit_and_delete_are_blocked_once_stock_has_been_shipped(): void
    {
        [$project, $product] = $this->project();
        $stockIn = $this->service->save($this->payload($project, [['product' => $product->id, 'quantity' => 60]]), 1);
        $this->inventory->transfer($project->id, $product->id, 3, 2, 50);
        $this->assertRejected(fn () => $this->service->destroy($stockIn->id), 'stock');
        $this->assertRejected(fn () => $this->service->save($this->payload($project, [['product' => $product->id, 'quantity' => 5]]), 1, $stockIn->id), 'stock');
        $this->assertSame(10, $this->stock($product));
        $this->assertSame(1, StockIn::count());
        $this->inventory->reverseTransfer($project->id, $product->id, 3, 2, 50);
        $this->service->destroy($stockIn->id);
        $this->assertSame(0, $this->stock($product));
    }

    public function test_one_document_can_hold_several_products(): void
    {
        [$project, $product] = $this->project();
        $other = Product::create(['project' => $project->id, 'name' => 'Meja', 'quantity' => 5]);
        $stockIn = $this->service->save($this->payload($project, [['product' => $product->id, 'quantity' => 10], ['product' => $other->id, 'quantity' => 5]]), 1);
        $this->assertSame(10, $this->stock($product));
        $this->assertSame(5, $this->stock($other));
        $this->assertSame(2, $stockIn->items->count());
    }

    public function test_validation_rules(): void
    {
        [$project, $product] = $this->project();
        [$legacy, $legacyProduct] = $this->project(10, false, 'OLD');
        [, $foreign] = $this->project(10, true, 'OTHER');
        $this->assertRejected(fn () => $this->service->save($this->payload($legacy, [['product' => $legacyProduct->id, 'quantity' => 1]]), 1), 'project');
        $this->assertRejected(fn () => $this->service->save($this->payload($project, [['product' => $foreign->id, 'quantity' => 1]]), 1), 'items.0.product');
        $this->assertRejected(fn () => $this->service->save($this->payload($project, [['product' => $product->id, 'quantity' => 1], ['product' => $product->id, 'quantity' => 2]]), 1), 'items.1');
        $this->assertRejected(fn () => $this->service->save($this->payload($project, [['product' => $product->id, 'quantity' => 1]], ['warehouse' => 1]), 1), 'warehouse');
        $this->assertRejected(fn () => $this->service->save($this->payload($project, [['product' => $product->id, 'quantity' => 1]], ['origin' => 3]), 1), 'origin');
        $this->assertRejected(fn () => $this->service->save($this->payload($project, [['product' => $product->id, 'quantity' => 1]], ['direction' => 'out']), 1), 'direction');
        $this->assertRejected(fn () => $this->service->save($this->payload($project, [['product' => $product->id, 'quantity' => 0]]), 1), 'items.0.quantity');
        $this->assertRejected(fn () => $this->service->save($this->payload($project, [['product' => $product->id, 'quantity' => 1]], ['files' => ['../etc/passwd']]), 1), 'files.0');
    }

    public function test_manual_stock_in_and_out_change_balance_on_save(): void
    {
        $item = ManualItem::create(['code' => 'KRT-1', 'name' => 'Kertas', 'unit' => 'rim']);
        $this->service->save($this->payload(null, [['manual_item' => $item->id, 'quantity' => 60]]), 1);
        $this->assertSame(60, (int) ManualInventory::where('manual_item', $item->id)->where('warehouse', 3)->value('quantity'));
        $this->service->save($this->payload(null, [['manual_item' => $item->id, 'quantity' => 25]], ['direction' => 'out', 'do_number' => 'OUT-1']), 1);
        $this->assertSame(35, (int) ManualInventory::where('manual_item', $item->id)->where('warehouse', 3)->value('quantity'));
    }

    public function test_manual_stock_out_is_rejected_when_balance_is_short_and_deleting_in_is_blocked(): void
    {
        $item = ManualItem::create(['code' => 'KRT-1', 'name' => 'Kertas', 'unit' => 'rim']);
        $in = $this->service->save($this->payload(null, [['manual_item' => $item->id, 'quantity' => 10]]), 1);
        $this->assertRejected(fn () => $this->service->save($this->payload(null, [['manual_item' => $item->id, 'quantity' => 11]], ['direction' => 'out', 'do_number' => 'OUT-1']), 1), 'stock');
        $this->assertSame(1, StockIn::count());
        $ok = $this->service->save($this->payload(null, [['manual_item' => $item->id, 'quantity' => 4]], ['direction' => 'out', 'do_number' => 'OUT-2']), 1);
        $this->assertRejected(fn () => $this->service->destroy($in->id), 'stock');
        $this->service->destroy($ok->id);
        $this->assertSame(10, (int) ManualInventory::where('manual_item', $item->id)->value('quantity'));
    }

    public function test_manual_documents_reject_project_products_and_inactive_items(): void
    {
        [, $product] = $this->project();
        $inactive = ManualItem::create(['code' => 'X', 'name' => 'Lama', 'unit' => 'pcs', 'active' => false]);
        $this->assertRejected(fn () => $this->service->save($this->payload(null, [['product' => $product->id, 'quantity' => 1]]), 1), 'items.0.manual_item');
        $this->assertRejected(fn () => $this->service->save($this->payload(null, [['manual_item' => $inactive->id, 'quantity' => 1]]), 1), 'items.0.manual_item');
        $this->assertRejected(fn () => $this->service->save($this->payload(null, [['manual_item' => $inactive->id, 'product' => $product->id, 'quantity' => 1]]), 1), 'items.0');
    }

    public function test_delivery_stock_is_checked_against_received_stock(): void
    {
        [$project, $product] = $this->project();
        $this->assertRejected(fn () => $this->inventory->assertAvailable($product->id, 3, 1), 'stock');
        $this->service->save($this->payload($project, [['product' => $product->id, 'quantity' => 30]]), 1);
        $this->inventory->assertAvailable($product->id, 3, 30);
        $this->assertRejected(fn () => $this->inventory->assertAvailable($product->id, 3, 31), 'stock');
    }

    public function test_stock_in_with_origin_is_a_transfer_limited_by_origin_stock(): void
    {
        [$project, $product] = $this->project(10);
        $this->service->save($this->payload($project, [['product' => $product->id, 'quantity' => 10]]), 1);
        $this->assertRejected(fn () => $this->service->save($this->payload($project, [['product' => $product->id, 'quantity' => 11]], ['warehouse' => 4, 'origin' => 3, 'do_number' => 'T-0']), 1), 'stock');
        $transfer = $this->service->save($this->payload($project, [['product' => $product->id, 'quantity' => 6]], ['warehouse' => 4, 'origin' => 3, 'do_number' => 'T-1']), 1);
        $this->assertSame(4, $this->stock($product, 3));
        $this->assertSame(6, $this->stock($product, 4));
        // The project-quantity cap does not apply to transfers.
        $this->service->save($this->payload($project, [['product' => $product->id, 'quantity' => 4]], ['warehouse' => 4, 'origin' => 3, 'do_number' => 'T-2']), 1);
        $this->assertSame(10, $this->stock($product, 4));
        $this->inventory->recalculateForProject($project->id);
        $this->assertSame(0, $this->stock($product, 3));
        $this->assertSame(10, $this->stock($product, 4));
        $this->inventory->transfer($project->id, $product->id, 4, 2, 5);
        $this->assertRejected(fn () => $this->service->destroy($transfer->id), 'stock');
        $this->assertRejected(fn () => $this->service->save($this->payload($project, [['product' => $product->id, 'quantity' => 1]], ['warehouse' => 1, 'origin' => 3]), 1), 'warehouse');
        $this->assertRejected(fn () => $this->service->save($this->payload($project, [['product' => $product->id, 'quantity' => 1]], ['origin' => 1, 'warehouse' => 3]), 1), 'origin');
    }

    public function test_deleting_a_transfer_restores_the_origin_stock(): void
    {
        [$project, $product] = $this->project(10);
        $this->service->save($this->payload($project, [['product' => $product->id, 'quantity' => 10]]), 1);
        $transfer = $this->service->save($this->payload($project, [['product' => $product->id, 'quantity' => 6]], ['warehouse' => 4, 'origin' => 3, 'do_number' => 'T-1']), 1);
        $this->service->destroy($transfer->id);
        $this->assertSame(10, $this->stock($product, 3));
        $this->assertSame(0, $this->stock($product, 4));
    }

    public function test_manual_stock_in_with_origin_moves_balance(): void
    {
        $item = ManualItem::create(['code' => 'KRT-1', 'name' => 'Kertas', 'unit' => 'rim']);
        $this->service->save($this->payload(null, [['manual_item' => $item->id, 'quantity' => 10]]), 1);
        $this->assertRejected(fn () => $this->service->save($this->payload(null, [['manual_item' => $item->id, 'quantity' => 11]], ['warehouse' => 4, 'origin' => 3, 'do_number' => 'T-0']), 1), 'stock');
        $this->service->save($this->payload(null, [['manual_item' => $item->id, 'quantity' => 7]], ['warehouse' => 4, 'origin' => 3, 'do_number' => 'T-1']), 1);
        $this->assertSame(3, (int) ManualInventory::where('manual_item', $item->id)->where('warehouse', 3)->value('quantity'));
        $this->assertSame(7, (int) ManualInventory::where('manual_item', $item->id)->where('warehouse', 4)->value('quantity'));
    }

    public function test_recalculation_for_stock_in_project_uses_stock_in_quantity_not_project_quantity(): void
    {
        [$project, $product] = $this->project(100);
        $this->service->save($this->payload($project, [['product' => $product->id, 'quantity' => 40]]), 1);
        $this->inventory->transfer($project->id, $product->id, 3, 2, 10);
        $product->update(['quantity' => 120]);
        DB::table('deliveries')->insert(['id' => 1, 'project' => $project->id, 'default_origin' => 3, 'destination' => 2, 'delivery_date' => '2026-10-06', 'do_number' => 'DO-1']);
        DB::table('delivery_items')->insert(['delivery' => 1, 'product' => $product->id, 'origin' => 3, 'destination' => 2, 'quantity' => 10, 'actual_quantity' => 10, 'delivered_at' => '2026-10-06 10:00:00']);
        $this->inventory->recalculateForProject($project->id);
        $this->assertSame(30, $this->stock($product, 3));
        $this->assertSame(10, $this->stock($product, 2));
        $this->assertSame(0, $this->stock($product, 1));
    }

    public function test_legacy_recalculation_is_unchanged(): void
    {
        [$project, $product] = $this->project(100, false);
        DB::table('projects')->where('id', $project->id)->update(['manufacture' => 1]);
        Inventory::create(['project' => $project->id, 'product' => $product->id, 'quantity' => 100, 'warehouse' => 1, 'warehouse_name' => 'Manufacture', 'product_name' => $product->name, 'storage' => false]);
        $this->inventory->recalculateForProject($project->id);
        $this->assertSame(100, $this->stock($product, 1));
    }
}
