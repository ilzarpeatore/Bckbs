@foreach($items as $item)
    <?php
    if ($item->hasChildren()){
        if ($item->children()->where('isActive', true)->first() !== null){
            $active = 'active';
        } else {
            $active = '';
        }
    } else {
        $active = '';
        if( $item->isActive == 'true' ) {
            $active = 'active';
        }
    }
    ?>
    <li class="nav-item" @lm_attrs($item) @if($item->hasChildren()) @endif @lm_endattrs>
        @if($item->link)
			<a @lm_attrs($item->link)
				@if($item->hasChildren()) data-bs-toggle="collapse" role="button" aria-expanded="{{ $active != '' ? 'true' : 'false' }}" aria-controls="{!! str_replace('#','',$item->url()) !!}" @else class="nav-link" @endif @lm_endattrs href="{!! $item->url() !!}">
				{!! $item->title !!}
				@if($item->hasChildren())
					<i class="right-icon">
						<svg xmlns="http://www.w3.org/2000/svg" width="18" fill="none" viewBox="0 0 24 24" stroke="currentColor">
							<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
						</svg>
					</i>
				@endif
			</a>
        @else
            <span class="item-name">{!! $item->title !!}</span>
        @endif
        @if($item->hasChildren())
            <ul class="sub-nav collapse {{ $active != '' ? 'show' : '' }}" id="{!! str_replace('#','',$item->url()) !!}" data-bs-parent="#sidebar">
                @include(config('laravel-menu.views.bootstrap-items'),array('items' => $item->children()))
            </ul>
        @endif
    </li>
    @if($item->divider)
        <li{!! Lavary\Menu\Builder::attributes($item->divider) !!}></li>
    @endif
@endforeach