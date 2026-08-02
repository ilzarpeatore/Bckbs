@if( $data['posting']->total() > 0 )
    @foreach ($data['posting'] as $posts)
        <div class="col-xl-8 col-lg-6 mb-4">
            <x-posting-card :post="$posts" :is_frontend="false" :heart="$data['heart']" :bookmark="$data['bookmark']"/>
        </div>
    @endforeach
@else
    <div class="d-flex justify-content-center flex-column align-items-center mt-5">
        <img src="{{ asset('frontend-section/images/no-data.png') }}" class="nodata-img">
        <p class="font-p mt-4 mb-5">{{ __('frontend::message.havent_any_data') }}</p>
    </div>
@endif