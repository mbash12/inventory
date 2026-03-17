<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class UpdateIsProduction extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'inventory:update-is-production';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Update is_production field on all products based on their associated project type';

    /**
     * Execute the console command.
     *
     * @return int
     */
    public function handle()
    {
        $this->info('Starting bulk update of is_production for products...');

        // Service-based types that are NOT production
        $serviceTypes = ['design', 'payment', 'supplier payment', 'supplier_payment'];

        // SET is_production = false for products whose project_type matches service types
        $falseCount = DB::table('products')
            ->join('projects', 'products.project', '=', 'projects.id')
            ->whereIn(DB::raw('LOWER(projects.project_type)'), $serviceTypes)
            ->update(['products.is_production' => false]);

        $this->info("Set is_production = FALSE for {$falseCount} products (Service types).");

        // SET is_production = true for products whose project type is a production type
        $trueCount = DB::table('products')
            ->join('projects', 'products.project', '=', 'projects.id')
            ->whereNotIn(DB::raw('LOWER(projects.project_type)'), $serviceTypes)
            ->update(['products.is_production' => true]);

        $this->info("Set is_production = TRUE for {$trueCount} products (Production types).");

        // Also set is_production = false for products with no associated project
        $noProjectCount = DB::table('products')
            ->whereNull('project')
            ->update(['products.is_production' => false]);

        $this->info("Set is_production = FALSE for {$noProjectCount} products with no project.");

        $total = $trueCount + $falseCount + $noProjectCount;
        $this->info("Done. Total rows updated: {$total}.");
        Log::info('inventory:update-is-production completed', [
            'is_production_true' => $trueCount,
            'is_production_false' => $falseCount,
            'no_project' => $noProjectCount,
        ]);

        return 0;
    }
}
