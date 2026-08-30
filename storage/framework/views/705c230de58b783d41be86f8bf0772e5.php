<!-- Backend Bundle JavaScript -->
<script src="<?php echo e(asset('js/libs.min.js')); ?>"></script>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array('data-table',$assets ?? [])): ?>
<script src="<?php echo e(asset('vendor/datatables/buttons.server-side.js')); ?>"></script>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array('chart',$assets ?? [])): ?>
    <!-- apexchart JavaScript -->
    <!-- <script src="<?php echo e(asset('js/charts/apexcharts.js')); ?>"></script> -->
    <!-- widgetchart JavaScript -->
    <!-- <script src="<?php echo e(asset('js/charts/widgetcharts.js')); ?>"></script> -->
    <script src="<?php echo e(asset('js/charts/dashboard.js')); ?>"></script>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<!-- fslightbox JavaScript -->
<script src="<?php echo e(asset('js/plugins/fslightbox.js')); ?>"></script>
<script src="<?php echo e(asset('js/plugins/slider-tabs.js')); ?>"></script>
<script src="<?php echo e(asset('js/plugins/form-wizard.js')); ?>"></script>

<!-- settings JavaScript -->
<script src="<?php echo e(asset('js/plugins/setting.js')); ?>"></script>

<script src="<?php echo e(asset('js/plugins/circle-progress.js')); ?>"></script>
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array('animation',$assets ?? [])): ?>
<!--aos javascript-->
<script src="<?php echo e(asset('vendor/aos/dist/aos.js')); ?>"></script>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array('phone',$assets ?? [])): ?>
    <script src="<?php echo e(asset('vendor/intlTelInput/js/intlTelInput-jquery.min.js')); ?>"></script>
    <script src="<?php echo e(asset('vendor/intlTelInput/js/intlTelInput.min.js')); ?>"></script>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<script src="<?php echo e(asset('vendor/swiper/swiper-bundle.min.js')); ?>"></script>

<script src="<?php echo e(asset('vendor/tinymce/js/tinymce/tinymce.min.js')); ?>"></script>
<script src="<?php echo e(asset('vendor/confirmJS/jquery-confirm.min.js')); ?>"></script>
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
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array('calender',$assets ?? [])): ?>
<!-- Fullcalender Javascript -->
<script src="<?php echo e(asset('vendor/fullcalendar/core/main.js')); ?>"></script>
<script src="<?php echo e(asset('vendor/fullcalendar/daygrid/main.js')); ?>"></script>
<script src="<?php echo e(asset('vendor/fullcalendar/timegrid/main.js')); ?>"></script>
<script src="<?php echo e(asset('vendor/fullcalendar/list/main.js')); ?>"></script>
<script src="<?php echo e(asset('vendor/fullcalendar/interaction/main.js')); ?>"></script>
<script src="<?php echo e(asset('vendor/moment.min.js')); ?>"></script>
<script src="<?php echo e(asset('js/plugins/calender.js')); ?>"></script>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>


<?php echo $__env->yieldPushContent('scripts'); ?>

<script src="<?php echo e(asset('js/plugins/prism.mini.js')); ?>"></script>

<!-- Custom JavaScript -->
<script src="<?php echo e(asset('js/hope-ui.js')); ?>"></script>
<script src="<?php echo e(asset('js/modelview.js')); ?>"></script>

<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(in_array('community',$assets ?? [])): ?>
<?php echo $__env->make('community.community-js', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?><?php /**PATH /var/www/testapp/resources/views/partials/dashboard/_scripts.blade.php ENDPATH**/ ?>