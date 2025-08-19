<?php

namespace App\Console\Commands;

use App\Models\Product;
use Illuminate\Console\Command;

class GenerateProductSlugs extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'products:generate-slugs {--force : Force regeneration of all slugs}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate slugs for products that don\'t have them';

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $this->info('Starting product slug generation...');

        $query = Product::query();
        
        if (!$this->option('force')) {
            $query->whereNull('slug')->orWhere('slug', '');
        }

        $products = $query->get();
        
        if ($products->isEmpty()) {
            $this->info('No products found that need slug generation.');
            return 0;
        }

        $this->info("Found {$products->count()} products to process.");

        $bar = $this->output->createProgressBar($products->count());
        $bar->start();

        $updated = 0;
        $errors = 0;

        foreach ($products as $product) {
            try {
                $oldSlug = $product->slug;
                $product->slug = $product->generateSlug();
                $product->save();
                
                if ($oldSlug !== $product->slug) {
                    $updated++;
                }
                
                $bar->advance();
            } catch (\Exception $e) {
                $errors++;
                $this->error("\nError processing product ID {$product->id}: " . $e->getMessage());
            }
        }

        $bar->finish();
        $this->newLine(2);

        $this->info("Slug generation completed!");
        $this->info("Updated: {$updated} products");
        
        if ($errors > 0) {
            $this->warn("Errors: {$errors} products");
        }

        return 0;
    }
}
