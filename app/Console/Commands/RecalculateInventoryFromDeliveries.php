<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Src\Models\Inventory;
use Src\Models\Project;
use Src\Services\InventoryService;

class RecalculateInventoryFromDeliveries extends Command
{
    protected $signature = 'inventory:recalculate-from-deliveries
                            {--project= : Project ID}
                            {--job= : Job number (exact or partial match)}
                            {--po= : Client PO number (exact or partial match)}
                            {--mismatches : Only projects where delivery qty differs from client inventory}
                            {--all : Recalculate every project that already has inventory}
                            {--dry-run : Show what would change without writing}';

    protected $description = 'Rebuild inventory quantities from delivery item movements (fixes delivered-but-stock-not-updated drift)';

    public function handle(InventoryService $inventoryService): int
    {
        $projects = $this->resolveProjects();
        if ($projects->isEmpty()) {
            $this->warn('No matching projects found.');
            return self::FAILURE;
        }

        $this->info(sprintf('Projects to process: %d%s', $projects->count(), $this->option('dry-run') ? ' (dry-run)' : ''));

        $fixed = 0;
        $unchanged = 0;

        foreach ($projects as $project) {
            $before = $this->snapshot($project->id);

            if ($this->option('dry-run')) {
                DB::beginTransaction();
                try {
                    $inventoryService->recalculateForProject((int) $project->id);
                    $after = $this->snapshot($project->id);
                    DB::rollBack();
                } catch (\Throwable $e) {
                    DB::rollBack();
                    $this->error("  #{$project->id} {$project->job_number}: {$e->getMessage()}");
                    continue;
                }
            } else {
                $inventoryService->recalculateForProject((int) $project->id);
                $after = $this->snapshot($project->id);
            }

            $changed = $before !== $after;
            if ($changed) {
                $fixed++;
            } else {
                $unchanged++;
            }

            $this->line(sprintf(
                '  #%d %s | PO %s | status=%s | %s',
                $project->id,
                $project->job_number,
                $project->client_po_number ?? '-',
                $project->status,
                $changed ? 'CHANGED' : 'ok'
            ));

            if ($changed || $this->output->isVerbose()) {
                $this->printSnapshot('before', $before);
                $this->printSnapshot($this->option('dry-run') ? 'would become' : 'after', $after);
            }
        }

        $this->newLine();
        $this->info("Done. changed={$fixed}, unchanged={$unchanged}");

        return self::SUCCESS;
    }

    private function resolveProjects()
    {
        if ($this->option('all')) {
            $ids = Inventory::query()->distinct()->pluck('project');
            return Project::whereIn('id', $ids)->orderBy('id')->get();
        }

        if ($this->option('mismatches')) {
            $ids = collect(DB::select("
                SELECT DISTINCT p.id
                FROM projects p
                JOIN products pr ON pr.project = p.id
                JOIN inventories inv
                  ON inv.project = p.id
                 AND inv.product = pr.id
                 AND inv.warehouse = 2
                 AND inv.deleted_at IS NULL
                LEFT JOIN (
                    SELECT d.project, di.product, SUM(di.actual_quantity) AS delivered_qty
                    FROM delivery_items di
                    JOIN deliveries d ON d.id = di.delivery
                    WHERE di.destination = 2
                      AND di.delivered_at IS NOT NULL
                    GROUP BY d.project, di.product
                ) del ON del.project = p.id AND del.product = pr.id
                WHERE inv.quantity != COALESCE(del.delivered_qty, 0)
            "))->pluck('id')->values();

            return Project::whereIn('id', $ids)->orderBy('id')->get();
        }

        $query = Project::query();
        $filtered = false;

        if ($id = $this->option('project')) {
            $query->where('id', (int) $id);
            $filtered = true;
        }
        if ($job = $this->option('job')) {
            $query->where('job_number', 'like', '%' . $job . '%');
            $filtered = true;
        }
        if ($po = $this->option('po')) {
            $query->where('client_po_number', 'like', '%' . $po . '%');
            $filtered = true;
        }

        if (!$filtered) {
            $this->error('Provide --project, --job, --po, --mismatches, or --all');
            return collect();
        }

        return $query->orderBy('id')->get();
    }

    private function snapshot(int $projectId): array
    {
        return Inventory::where('project', $projectId)
            ->orderBy('product')
            ->orderBy('warehouse')
            ->get(['product', 'warehouse', 'quantity', 'storage', 'warehouse_name'])
            ->map(fn ($row) => [
                'product' => (int) $row->product,
                'warehouse' => (int) $row->warehouse,
                'warehouse_name' => $row->warehouse_name,
                'quantity' => (int) $row->quantity,
                'storage' => (bool) $row->storage,
            ])
            ->values()
            ->all();
    }

    private function printSnapshot(string $label, array $rows): void
    {
        $this->line("    {$label}:");
        if (empty($rows)) {
            $this->line('      (no inventory rows)');
            return;
        }
        foreach ($rows as $row) {
            $this->line(sprintf(
                '      product=%d wh=%d (%s) qty=%d storage=%s',
                $row['product'],
                $row['warehouse'],
                $row['warehouse_name'],
                $row['quantity'],
                $row['storage'] ? 'Y' : 'N'
            ));
        }
    }
}
