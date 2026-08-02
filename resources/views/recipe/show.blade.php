@push('scripts')
    <script>
        (function($) {
            let draggedRow = null;
            $('#sortable-steps').on('dragstart', 'tr', function() {
                draggedRow = this;
                $(this).addClass('dragging');
            });

            $('#sortable-steps').on('dragend', 'tr', function() {
                $(this).removeClass('dragging');
                draggedRow = null;
            });

            $('#sortable-steps').on('dragover', 'tr', function(e) {
                e.preventDefault();
                if (!draggedRow || this === draggedRow) return;
                const rect = this.getBoundingClientRect();
                const offset = e.originalEvent.clientY - rect.top;
                const midpoint = rect.height / 2;

                const tbody = $(this).closest('tbody')[0];

                tbody.insertBefore(
                    draggedRow,
                    offset > midpoint ? this.nextSibling : this
                );
            });           

        })(jQuery);
    </script>
@endpush

<x-app-layout :assets="$assets ?? []">
    <div class="container-fluid">
        <div class="row">
            <div class="col-lg-12">
                <div class="card card-block card-stretch border-radius-20">
                    <div class="card-body p-0">
                        <div class="d-flex justify-content-between align-items-center p-3">
                            <h5 class="font-weight-bold">{{ $recipe->title }}</h5>
                            <div>
                                <a href="{{ route('recipe.index') }}" class="float-end ms-1 btn btn-sm btn-primary">{{ __('message.back') }}</a>
                                <a href="{{ route('recipe.edit', $recipe->id) }}" class="float-end btn btn-sm btn-primary"></i> {{ __('message.edit') }}</a>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            {{-- LEFT SIDE --}}
            <div class="col-lg-8">
                {{-- BASIC INFO --}}
                <div class="card mb-3">
                    <div class="card-body">
                        <p><strong>{{ __('message.preparation_time') }} : </strong> {{ $recipe->preparation_time }}
                            {{ __('message.minutes') }}</p>
                        <p><strong>{{ __('message.type') }} : </strong> {{ ucfirst($recipe->type) }}</p>
                        <p><strong>{{ __('message.meal_type') }} : </strong>                            
                            {{ implode(', ', array_map(fn($m) => __('message.'.$m), (array) $recipe->meal_type)) }}
                        </p>
                        <p><strong>{{ __('message.recipecategory') }} : </strong>
                            {{ $recipe->categories->implode('title', ', ') }}
                        </p>
                        <p><strong>{{ __('message.recipetag') }} : </strong>
                            {{ $recipe->tags->implode('title', ', ') }}
                        </p>
                    </div>
                </div>

                {{-- DESCRIPTION --}}
                <div class="card mb-3">
                    <div class="card-header">
                        <strong>{{ __('message.description') }}</strong>
                    </div>
                    <div class="card-body">
                        {!! $recipe->description ?? '-' !!}
                    </div>
                </div>
            </div>

            {{-- RIGHT SIDE --}}
            <div class="col-lg-4">

                {{-- IMAGE --}}
                <div class="card mb-3">
                    <div class="card-body text-center">
                        @if (getMediaFileExit($recipe, 'recipe_image'))
                            <img src="{{ getSingleMedia($recipe, 'recipe_image') }}" class="rounded img-fluid avatar-130">
                        @else
                            <span class="text-muted">{{ __('message.no_image') }}</span>
                        @endif
                    </div>
                </div>

                {{-- NUTRITION --}}
                <div class="card">
                    <div class="card-header">
                        <strong>{{ __('message.nutrition') }}</strong>
                    </div>
                    <div class="card-body">
                        <p>{{ __('message.calories') }} : {{ $recipe->calories }}</p>
                        <p>{{ __('message.protein') }} : {{ $recipe->protein }}</p>
                        <p>{{ __('message.fat') }} : {{ $recipe->fats }}</p>
                        <p>{{ __('message.carbs') }} : {{ $recipe->carbs }}</p>
                    </div>
                </div>
            </div>
        </div>
        <div class="row">
            <div class="col-lg-12">
                {{-- STEPS --}}
                @if($recipe->steps->count() > 0)
                    <div class="card mb-3">
                        <div class="card-header d-flex justify-content-between mb-3">
                            <strong>{{ __('message.recipe_preparation_step') }}</strong>
                            <small class="text-muted">
                                {{ __('message.drag_steps_reorder') }}
                            </small>
                        </div>
                        <div class="card-body p-0">
                            {{ html()->form('POST', route('recipe.steps.reorder'))->open() }}
                            <table class="table mb-0">
                                {{ html()->hidden('recipe_id', $recipe->id ?? null)->class('form-control') }}
                                <tbody id="sortable-steps">
                                    @foreach ($recipe->steps as $step)
                                        <tr draggable="true" class="draggable-row">
                                            <td width="10" class="pe-2">
                                                <svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 16 16" fill="currentColor">
                                                    <rect y="2" width="16" height="2"></rect>
                                                    <rect y="7" width="16" height="2"></rect>
                                                    <rect y="12" width="16" height="2"></rect>
                                                </svg>
                                            </td>
                                            <td class="ps-2">
                                                <input type="hidden" name="id[]" value="{{$step->id}}">
                                                <strong>{{ __('message.step') }} {{ $step->sequence }} :</strong>
                                                {{ $step->instruction }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                            <div class="p-2">
                                {{ html()->submit( __('message.save') )->class('btn btn-md btn-primary float-end') }}
                            </div>
                            {{ html()->form()->close() }}
                        </div>
                    </div>
                @endif
                {{-- INGREDIENTS --}}
                <div class="card mb-3">
                    <div class="card-header mb-3">
                        <strong>{{ __('message.recipe_ingredient') }}</strong>
                    </div>
                    <div class="card-body p-0">
                        <div class="table-responsive">
                        <table class="table mb-0">
                            <thead>
                                <tr>
                                    <th>{{ __('message.ingredient') }}</th>
                                    <th>{{ __('message.measurement_units') }}</th>
                                    <th>{{ __('message.qty') }}</th>
                                    <th>{{ __('message.gram_unit') }}</th>
                                    <th>{{ __('message.display_qty') }}</th>
                                    <th>{{ __('message.grams') }}</th>
                                    {{--
                                    <th>{{ __('message.calories') }}</th>
                                    <th>{{ __('message.protein') }}</th>
                                    <th>{{ __('message.fat') }}</th>
                                    <th>{{ __('message.carbs') }}</th>
                                    --}}
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($recipe->recipeIngredients as $ing)
                                    <tr>
                                        <td>{{ optional($ing->ingredient)->title }}</td>
                                        <td>{{ optional($ing->measurementUnit)->title ?? '-' }}</td>
                                        <td>{{ $ing->quantity }}</td>
                                        <td>{{ round($ing->amount, 3) ?? '-' }}</td>
                                        <td>
                                            <span class="badge bg-soft-primary">
                                                {{  $ing->display_quantity  }}
                                            </span>
                                        </td>
                                        <td>{{ round($ing->quantity_grams, 3) }}g</td>
                                        {{-- <td>{{ round($ing->calories) }}</td>
                                        <td>{{ round($ing->protein, 1) }}g</td>
                                        <td>{{ round($ing->fats, 1) }}g</td>
                                        <td>{{ round($ing->carbs, 1) }}g</td> --}}
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <style>
        .draggable-row {
            cursor: move;
        }

        .draggable-row.dragging {
            opacity: 0.5;
            background: var(--bs-primary);
        }
    </style>
</x-app-layout>
