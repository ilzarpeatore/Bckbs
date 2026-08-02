<?php
    $language_option = appSettingData('get')->language_option;
    $language_array = languagesArray($language_option);
    $files = [ 'message' ];
?>
<div class="row">
    <div class="col-md-4">
        <div class="form-group">
            <label for="language_option" class="form-control-label">{{ __('message.language_option') }}</label>
            <select class="form-control select2js" name="language_option" id='change_language'>
                @if(count($language_array) > 0)
                    @foreach( $language_array  as $lang )
                        <option value="{{$lang['id']}}" {{ config('app.locale') == $lang['id']  ? 'selected' : '' }} >{{$lang['title']}}</option>
                    @endforeach
                @endif
            </select>
        </div>
    </div>
    <div class="col-md-12">
        <div class="language-section"></div>
    </div>
</div>
<script>
    function getLanguageFile(lang = ''){
        var url = "{{ route('getLanguageFile') }}";
        $.ajax({
            type: 'post',
            url: url,
            data: {
                'lang': lang,
                'file': 'message',
                'type': 'frontend_msg', 
            },
            success: function(res){
                $('.language-section').html(res);
            }
        });
    }
    $(document).ready(function (){
        $('.select2js').select2();
        let selectedLang = $("#change_language :selected").val();
        getLanguageFile(selectedLang)
        $(document).on('change','#change_language',function() {
            let selectedLang = $("#change_language :selected").val();
            getLanguageFile(selectedLang)
        });
    });
</script>
