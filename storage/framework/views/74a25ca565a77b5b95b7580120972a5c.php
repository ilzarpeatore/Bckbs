<?php if (isset($component)) { $__componentOriginal69dc84650370d1d4dc1b42d016d7226b = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal69dc84650370d1d4dc1b42d016d7226b = $attributes; } ?>
<?php $component = App\View\Components\GuestLayout::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('guest-layout'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\GuestLayout::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
    <div class="container">
        <div class="row no-gutters height-self-center">
            <div class="col-sm-12 text-center align-self-center">
                <div class="mm-error position-relative">
                     <img src="<?php echo e(asset('images/error/404-page.png')); ?>" class="img-fluid mm-error-img mx-auto" alt="404">
                    <img src="<?php echo e(asset('images/error/404-page-dark.png')); ?>" class="img-fluid mm-error-img mm-error-img-dark mx-auto" alt="404">
                    <h2 class="mb-0 mt-4"><?php echo e(__('message.error_404_title')); ?></h2>
                    <p><?php echo e(__('message.error_404_description')); ?></p>
                    <a class="btn btn-primary d-inline-flex align-items-center mt-3" href="<?php echo e(route('dashboard')); ?>"><?php echo e(__('message.back_to_home')); ?></a>
                </div>
            </div>
        </div>
   </div>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal69dc84650370d1d4dc1b42d016d7226b)): ?>
<?php $attributes = $__attributesOriginal69dc84650370d1d4dc1b42d016d7226b; ?>
<?php unset($__attributesOriginal69dc84650370d1d4dc1b42d016d7226b); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal69dc84650370d1d4dc1b42d016d7226b)): ?>
<?php $component = $__componentOriginal69dc84650370d1d4dc1b42d016d7226b; ?>
<?php unset($__componentOriginal69dc84650370d1d4dc1b42d016d7226b); ?>
<?php endif; ?><?php /**PATH /var/www/testapp/resources/views/errors/route404.blade.php ENDPATH**/ ?>