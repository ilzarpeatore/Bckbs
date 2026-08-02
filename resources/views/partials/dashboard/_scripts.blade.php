<!-- Backend Bundle JavaScript -->
<script src="{{ asset('js/libs.min.js')}}"></script>
@if(in_array('data-table',$assets ?? []))
<script src="{{ asset('vendor/datatables/buttons.server-side.js')}}"></script>
@endif
@if(in_array('chart',$assets ?? []))
    <!-- apexchart JavaScript -->
    <!-- <script src="{{asset('js/charts/apexcharts.js') }}"></script> -->
    <!-- widgetchart JavaScript -->
    <!-- <script src="{{asset('js/charts/widgetcharts.js') }}"></script> -->
    <script src="{{asset('js/charts/dashboard.js') }}"></script>
@endif

<!-- fslightbox JavaScript -->
<script src="{{asset('js/plugins/fslightbox.js')}}"></script>
<script src="{{asset('js/plugins/slider-tabs.js') }}"></script>
<script src="{{asset('js/plugins/form-wizard.js')}}"></script>

<!-- settings JavaScript -->
<script src="{{asset('js/plugins/setting.js')}}"></script>

<script src="{{asset('js/plugins/circle-progress.js') }}"></script>
@if(in_array('animation',$assets ?? []))
<!--aos javascript-->
<script src="{{asset('vendor/aos/dist/aos.js')}}"></script>
@endif

@if(in_array('phone',$assets ?? []))
    <script src="{{ asset('vendor/intlTelInput/js/intlTelInput-jquery.min.js') }}"></script>
    <script src="{{ asset('vendor/intlTelInput/js/intlTelInput.min.js') }}"></script>
@endif

<script src="{{ asset('vendor/swiper/swiper-bundle.min.js') }}"></script>

<script src="{{ asset('vendor/tinymce/js/tinymce/tinymce.min.js') }}"></script>
<script src="{{ asset('vendor/confirmJS/jquery-confirm.min.js') }}"></script>
<script>
    // Text Editor code
    if (typeof(tinyMCE) != "undefined") {
        // tinymceEditor()
        function tinymceEditor(target, button, height = 200) {
            var rtl = $("html[lang=ar]").attr('dir');
            tinymce.init({
                selector: target || '.textarea',
                directionality : rtl,
                height: height,
                plugins: [ 'advlist', 'autolink', 'lists', 'link', 'image', 'charmap', 'preview', 'anchor', 'searchreplace', 'visualblocks', 'code', 'fullscreen', 'insertdatetime', 'media', 'table', 'help', 'wordcount' ],
                toolbar: 'undo redo | blocks | bold italic backcolor | alignleft aligncenter alignright alignjustify | bullist numlist outdent indent | removeformat | help',
                content_style: 'body { font-family:Helvetica,Arial,sans-serif; font-size:16px }',
                automatic_uploads: false,
            });
        }
    }
</script>
@if(in_array('calender',$assets ?? []))
<!-- Fullcalender Javascript -->
<script src="{{asset('vendor/fullcalendar/core/main.js')}}"></script>
<script src="{{asset('vendor/fullcalendar/daygrid/main.js')}}"></script>
<script src="{{asset('vendor/fullcalendar/timegrid/main.js')}}"></script>
<script src="{{asset('vendor/fullcalendar/list/main.js')}}"></script>
<script src="{{asset('vendor/fullcalendar/interaction/main.js')}}"></script>
<script src="{{asset('vendor/moment.min.js')}}"></script>
<script src="{{asset('js/plugins/calender.js')}}"></script>
@endif


@stack('scripts')

<script src="{{asset('js/plugins/prism.mini.js')}}"></script>

<!-- Custom JavaScript -->
<script src="{{asset('js/hope-ui.js') }}"></script>
<script src="{{asset('js/modelview.js')}}"></script>

@if(in_array('community',$assets ?? []))
@include('community.community-js')
@endif