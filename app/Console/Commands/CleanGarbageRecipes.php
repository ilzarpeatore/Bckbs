<?php

namespace App\Console\Commands;

use App\Models\Recipe;
use Illuminate\Console\Command;

class CleanGarbageRecipes extends Command
{
    protected $signature = 'recipes:clean-garbage';
    protected $description = 'Remove recipes with empty ingredients (parse errors)';

    public function handle(): int
    {
        $garbage = Recipe::where(function ($q) {
            $q->where('title', 'regexp', '^[a-z].*(cuchar|minut|horno|nevera|refrigerador|olla|fuego|sartén|sarten|batidor)')
              ->orWhere('title', 'regexp', '^(agregar|añadir|poner|colocar|retirar|dejar|cocinar|calentar|mezclar|batir|pelar|lavar|cortar|trocear|picar|espolvorear|servir|revolver|bajar|subir|tapar|destapar|escurrir|rallar|disolver|incorporar|verter|reservar|decorar|acompañar)');
        })
        ->where('calories', 0)
        ->where(function ($q) {
            $q->where('description', 'not like', '%INGREDIENTES:%')
              ->orWhereRaw("LENGTH(description) - LENGTH(REPLACE(description, 'INGREDIENTES:', '')) < 20");
        });

        $count = $garbage->count();
        if ($count > 0) {
            $garbage->delete();
            $this->info("Deleted {$count} garbage recipes");
        }

        // Also delete recipes with empty descriptions
        $empty = Recipe::whereNull('description')->orWhere('description', '')->orWhere('description', 'like', "INGREDIENTES:\n\n\nPREPARACIÓN:\n")->where('calories', 0);
        $eCount = $empty->count();
        if ($eCount > 0) {
            $empty->delete();
            $this->info("Deleted {$eCount} empty-description recipes");
        }

        $this->info("Remaining: " . Recipe::count());
        return 0;
    }
}
