<?php

namespace App\Http\Controllers;

use App\DataTables\RecipeDataTable;
use App\Http\Requests\RecipeRequest;
use App\Models\Recipe;
use App\Models\RecipeCategory;
use App\Models\RecipeTag;
use App\Models\RecipeStep;
use Illuminate\Http\Request;
use App\Helpers\AuthHelper;
use App\Traits\HandlesRecipeIngredients;

class RecipeController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    use HandlesRecipeIngredients;
    public function index(RecipeDataTable $dataTable)
    {
        $pageTitle = __('message.list_form_title', ['form' => __('message.recipe')]);
        $auth_user = AuthHelper::authSession();
        if (!$auth_user->can('recipe-list')) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }
        $assets = ['data-table'];

        $headerAction = $auth_user->can('recipe-add') ? '<a href="' . route('recipe.create') . '" class="btn btn-sm btn-primary" role="button">' . __('message.add_form_title', ['form' => __('message.recipe')]) . '</a>' : '';

        $categories = RecipeCategory::where('status', 'active')->orderBy('title')->get();
        $tags = RecipeTag::where('status', 'active')->orderBy('title')->get();

        $include_filter_file = view('recipe.filter', compact('categories', 'tags'))->render();

        return $dataTable->with(['auth_user' => $auth_user])->render('global.datatable', compact('pageTitle', 'auth_user', 'assets', 'headerAction', 'include_filter_file'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        if (!auth()->user()->can('recipe-add')) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }
        return view('recipe.form', [
            'data' => new Recipe(),
            'id' => null,
            'pageTitle' => __('message.add_form_title', ['form' => __('message.recipe')]),
        ]);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(RecipeRequest $request)
    {        
        $recipe = Recipe::create([
            'title' => $request->title,
            'preparation_time' => $request->preparation_time,
            'type' => $request->type,
            'meal_type' => $request->meal_type,
            'description' => $request->description,
            'video_url' => $request->video_url,
            'status' => $request->status,
            'calories' => 0,
            'protein' => 0,
            'fats' => 0,
            'carbs' => 0,
        ]);

        if ($request->hasFile('recipe_image')) {
            storeMediaFile($recipe, $request->recipe_image, 'recipe_image');
        }

        $recipe->categories()->sync($request->recipe_category_ids ?? []);
        $recipe->tags()->sync($request->recipe_tag_ids ?? []);

        //Steps
        if ($request->filled('instruction')) {
            $sequence = 1;
            foreach ($request->instruction as $i => $instruction) {
                if (blank($instruction)) {
                    continue;
                }
                RecipeStep::create([
                    'recipe_id' => $recipe->id,
                    'instruction' => $instruction,
                    'sequence' => $sequence++,
                ]);
            }
        }
        $this->saveRecipeIngredient($recipe, $request->ingredients);       

        return redirect()->route('recipe.index')->withSuccess(__('message.save_form', ['form' => __('message.recipe')]));
    }

    /**
     * Display the specified resource.
     */
    public function show($id)
    {
        if (!auth()->user()->can('recipe-show')) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $recipe = Recipe::with(['categories', 'tags', 'steps', 'recipeIngredients.ingredient', 'recipeIngredients.measurementUnit'])->findOrFail($id);
        return view('recipe.show', compact('recipe'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit($id)
    {
        if (!auth()->user()->can('recipe-edit')) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $data = Recipe::with(['categories', 'tags', 'recipeIngredients.ingredient', 'recipeIngredients.measurementUnit', 'steps'])->findOrFail($id);
       
        $selected_recipecategory = $data->categories->pluck('title', 'id')->toArray();
        $selected_recipetag = $data->tags->pluck('title', 'id')->toArray();

        $pageTitle = __('message.update_form_title', ['form' => __('message.recipe')]);

        return view('recipe.form', compact('data', 'id', 'pageTitle', 'selected_recipecategory', 'selected_recipetag'));
    }

    /**
     * Update the specified resource in storage.    
     */
    public function update(RecipeRequest $request, $id)
    {
        $recipe = Recipe::findOrFail($id);
        $recipe->update([
            'title' => $request->title,
            'preparation_time' => $request->preparation_time,
            'type' => $request->type,
            'meal_type' => $request->meal_type,
            'description' => $request->description,
            'video_url' => $request->video_url,
            'status' => $request->status,
        ]);

        if ($request->hasFile('recipe_image')) {
            $recipe->clearMediaCollection('recipe_image');
            storeMediaFile($recipe, $request->recipe_image, 'recipe_image');
        }

        $recipe->categories()->sync($request->recipe_category_ids ?? []);
        $recipe->tags()->sync($request->recipe_tag_ids ?? []);
        
        $stepIds = [];
        
        if ($request->filled('instruction')) {
            $sequence = 1;
            foreach ($request->instruction as $i => $instruction) {
                if (trim($instruction) === '') continue;
                $stepId = $request->recipe_steps_id[$i] ?? null;
                $stepIds[] = $stepId;
                $recipe_step_data = [
                    'recipe_id'     => $recipe->id,
                    'instruction'   => $instruction,
                    'sequence'      => $sequence++,
                ];

                RecipeStep::updateOrCreate(['id' => $stepId], $recipe_step_data);
            }
        }

        RecipeStep::where('recipe_id', $recipe->id)->whereNotIn('id', $stepIds)->delete();

        $this->saveRecipeIngredient($recipe, $request->ingredients);

        return redirect()->route('recipe.index')->withSuccess(__('message.update_form', ['form' => __('message.recipe')]));
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy($id)
    {
        if (!auth()->user()->can('recipe-delete')) {
            $message = __('message.permission_denied_for_account');
            return redirect()->back()->withErrors($message);
        }

        $recipe = Recipe::findOrFail($id);
        $status = 'errors';
        $message = __('message.not_found_entry', ['name' => __('message.recipe')]);

        if ($recipe != '') {
            $recipe->delete();
            $status = 'success';
            $message = __('message.delete_form', ['form' => __('message.recipe')]);
        }

        if (request()->ajax()) {
            return response()->json(['status' => true, 'message' => $message]);
        }

        return redirect()->back()->with($status, $message);
    }

    public function reorderSteps(Request $request)
    {
        $recipe_id = request('recipe_id');

        if(count($request->id) > 0){
            foreach($request->id as $key => $value){
                $sequence = $key + 1;
                RecipeStep::where('id', $value)->update( [
                    'sequence' => $sequence
                ]);
            }
        }
        $message = __('message.update_form',['form' => __('message.sequence')]);
        

        return redirect()->route('recipe.show', $recipe_id)->withSuccess($message);
    }

}
