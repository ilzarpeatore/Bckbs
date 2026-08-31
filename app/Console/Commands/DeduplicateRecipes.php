<?php

namespace App\Console\Commands;

use App\Models\Recipe;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class DeduplicateRecipes extends Command
{
    protected $signature = 'recipes:deduplicate';
    protected $description = 'Remove duplicate recipes keeping best version';

    public function handle(): int
    {
        // 1. Delete recipes with garbage titles (steps as titles)
        $garbage = Recipe::where('title', 'regexp', '^[a-z].*(cuchara|minutos|horno|sartén|nevera|refrigerador|batidor|olla|fuego)')
            ->orWhere('title', 'regexp', '^(agregar|añadir|poner|colocar|retirar|dejar|cocinar|calentar|mezclar|batir|pelar|lavar|cortar|trocear|picar|espolvorear|servir)')
            ->orWhere('title', 'regexp', '^(si se|cuando|mientras|antes de|después|una vez|por último|finalmente)');
        
        $garbageCount = $garbage->count();
        if ($garbageCount > 0) {
            $garbage->delete();
            $this->info("Deleted {$garbageCount} garbage titles");
        }

        // 2. Find duplicates and keep the one with longest description
        $dupes = DB::table('recipes')
            ->select('title', DB::raw('COUNT(*) as cnt'), DB::raw('MAX(id) as keep_id'))
            ->groupBy('title')
            ->having('cnt', '>', 1)
            ->get();

        $deleted = 0;
        foreach ($dupes as $d) {
            // Find all duplicates for this title
            $records = Recipe::where('title', $d->title)->orderBy('id')->get();
            
            // Keep the one with longest description, break ties with highest ID
            $best = $records->sortByDesc(fn($r) => strlen($r->description ?? ''))->first();
            
            // Delete the rest
            foreach ($records as $r) {
                if ($r->id !== $best->id) {
                    $r->delete();
                    $deleted++;
                }
            }
        }

        $this->info("Deleted {$deleted} duplicate recipes");
        $this->info("Remaining: " . Recipe::count() . " unique recipes");
        return 0;
    }
}
