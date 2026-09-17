<!DOCTYPE html>
<html lang="<?php echo e(str_replace('_', '-', app()->getLocale())); ?>" dir="<?php echo e(mighty_language_direction()); ?>">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="<?php echo e(csrf_token()); ?>">

        <title><?php echo e(config('app.name', 'Laravel')); ?></title>

        <link rel="shortcut icon" class="site_favicon_preview" href="<?php echo e(getSingleMedia(appSettingData('get'), 'site_favicon', null)); ?>" />
        
        <!-- Fonts -->
        <!-- <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700&display=swap"> -->

        <!-- Styles -->
        <link rel="stylesheet" href="<?php echo e(asset('css/libs.min.css')); ?>">
        <link rel="stylesheet" href="<?php echo e(asset('css/hope-ui.css?v=1.0')); ?>">
        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if(mighty_language_direction() == 'rtl'): ?>
            <link rel="stylesheet" href="<?php echo e(asset('css/rtl.css')); ?>">
        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
        <!-- remixicon -->
        <!-- <link rel="stylesheet" href="<?php echo e(asset('vendor/remixicon/fonts/remixicon.css')); ?>"/> -->

    </head>
    <body class=" " data-bs-spy="scroll" data-bs-target="#elements-section" data-bs-offset="0" tabindex="0">
        <div id="loading">
            <?php echo $__env->make('partials.dashboard._body_loader', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
            <?php echo $__env->make('partials.dashboard._head', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
        </div>
        <div class="wrapper">
            <?php echo e($slot); ?>

        </div>
         <?php echo $__env->make('partials.dashboard._scripts', array_diff_key(get_defined_vars(), ['__data' => 1, '__path' => 1]))->render(); ?>
    </body>
</html>
<?php /**PATH /home/user/Bckbs/resources/views/layouts/dashboard/guest.blade.php ENDPATH**/ ?>