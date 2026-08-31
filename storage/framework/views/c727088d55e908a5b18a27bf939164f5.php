

<!-- Favicon -->
<link rel="shortcut icon" class="site_favicon_preview" href="<?php echo e(getSingleMedia(appSettingData('get'), 'site_favicon', null)); ?>" />
<link rel="stylesheet" href="<?php echo e(asset('css/libs.min.css')); ?>">
<link rel="stylesheet" href="<?php echo e(asset('css/hope-ui.css?v=1.1.0')); ?>">
<link rel="stylesheet" href="<?php echo e(asset('vendor/confirmJS/jquery-confirm.min.css')); ?>"/>
<link rel="stylesheet" href="<?php echo e(asset('css/custom.css?v=1.1.0')); ?>">
<link rel="stylesheet" href="<?php echo e(asset('css/customizer.css?v=1.1.0')); ?>">
<link rel="stylesheet" href="<?php echo e(asset('css/dark.css?v=1.1.0')); ?>">
<?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(mighty_language_direction() == 'rtl'): ?>
   <link rel="stylesheet" href="<?php echo e(asset('css/rtl.css?v=1.1.0')); ?>">
<?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>

<link rel="stylesheet" href="<?php echo e(asset('vendor/intlTelInput/css/intlTelInput.css')); ?>">
<link rel="stylesheet" href="<?php echo e(asset('vendor/swiper/swiper-bundle.min.css')); ?>">

<!-- Fullcalender CSS -->
<link rel='stylesheet' href="<?php echo e(asset('vendor/fullcalendar/core/main.css')); ?>" />
<link rel='stylesheet' href="<?php echo e(asset('vendor/fullcalendar/daygrid/main.css')); ?>" />
<link rel='stylesheet' href="<?php echo e(asset('vendor/fullcalendar/timegrid/main.css')); ?>" />
<link rel='stylesheet' href="<?php echo e(asset('vendor/fullcalendar/list/main.css')); ?>" />
<link rel="stylesheet" href="<?php echo e(asset('vendor/Leaflet/leaflet.css')); ?>" />
<link rel="stylesheet" href="<?php echo e(asset('css/custom-css.css')); ?>">

<link rel="stylesheet" href="<?php echo e(asset('vendor/aos/dist/aos.css')); ?>" />

<style>
    th.hide-search input{
       display: none;
    }
 </style>
<?php /**PATH /var/www/testapp/resources/views/partials/dashboard/_head.blade.php ENDPATH**/ ?>