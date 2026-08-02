<!-- Modal -->
<div class="row">
    <div class="col-md-12">
        <div class="form-group">
            <div class="mt-2">
                <div class="row">
                    <div class="col-md-12">
                        <ul class="">
                            <li class="mt-1">{{ __('message.download_template_title') }}</li>
                        </ul>
                    </div>
                </div>
                <div class="row">
                    <div class="col-md-12">
                        <ol class="ml-4">
                            <li>{{ __('message.goto') }} <a href="{{ route("languagewithkeyword.index") }}">“{{ __('message.language_with_keyword_list') }}” </a>{{ __('message.section') }}</li>
                            <li>{{ __('message.download_template_value_one') }} <span class="font-weight-bold">"{{ __('message.keyword_value')  }}" </span> {{ __('message.download_template_value_two') }}</li>
                        </ol>
                        <div class="mt-2">
                            <img src="{{ asset('images/downloadtemplate.png') }}" alt="downloadtemplate" class="mt-1" style="width:100%">
                        </div>
                        <ol class="ml-4" start="3">
                            <li>{{ __('message.download_template_value_three') }}</li>
                            <li>{{ __('message.download_template_value_four') }}</li>
                            <li>{{ __('message.download_template_value_five') }} <span class="font-weight-bold">"{{ __('message.keyword_value')  }}" </span> {{ __('message.download_template_value_six') }} </li>
                            <li>{{ __('message.download_template_value_seven') }}</li>
                            <li>{{ __('message.download_template_value_eight') }}</li>
                        </ol>
                    </div>
                </div>
            </div>
            <div class="row">
                <div class="col-md-12">
                    <div class="mb-2">
                        <h6 class="mb-2">{{ __('message.download_template_value_nine') }}  <span class="font-weight-bold">"{{ __('message.keyword_value')  }}" </span> {{ __('message.download_template_value_ten') }}
                        </h6>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>