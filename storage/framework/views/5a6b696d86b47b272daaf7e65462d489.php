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
   <section class="login-content">
      <div class="row m-0 align-items-center bg-white vh-100">
         <div class="col-md-6 d-md-block d-none bg-primary p-0 mt-n1 vh-100 overflow-hidden">
            <img src="<?php echo e(asset('images/auth/02.png')); ?>" class="img-fluid gradient-main animated-scaleX" alt="images">
         </div>
         <div class="col-md-6 p-0">               
            <div class="card card-transparent auth-card shadow-none d-flex justify-content-center mb-0">
               <div class="card-body">
                     <div class="auth-logo ">
                        <a href="<?php echo e(route('dashboard')); ?>" class="navbar-brand d-flex align-items-center">
                           <img src="<?php echo e(getSingleMedia(appSettingData('get'),'site_logo',null)); ?>" class="img-fluid mode light-img rounded-normal light-logo site_logo_preview" alt="logo">                             
                           <img src="<?php echo e(getSingleMedia(appSettingData('get'),'site_dark_logo',null)); ?>" class="img-fluid mode dark-img rounded-normal darkmode-logo site_dark_logo_preview" alt="dark-logo">                              
                        </a>
                     </div>
                  <h4 class="mb-2 text-right"><?php echo e(__('auth.reset_password')); ?></h4>
                  <p><?php echo e(__('auth.reset_password_description')); ?></p>
                  
                  <?php if (isset($component)) { $__componentOriginal7c1bf3a9346f208f66ee83b06b607fb5 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal7c1bf3a9346f208f66ee83b06b607fb5 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.auth-session-status','data' => ['class' => 'mb-4','status' => session('status')]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('auth-session-status'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'mb-4','status' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute(session('status'))]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal7c1bf3a9346f208f66ee83b06b607fb5)): ?>
<?php $attributes = $__attributesOriginal7c1bf3a9346f208f66ee83b06b607fb5; ?>
<?php unset($__attributesOriginal7c1bf3a9346f208f66ee83b06b607fb5); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal7c1bf3a9346f208f66ee83b06b607fb5)): ?>
<?php $component = $__componentOriginal7c1bf3a9346f208f66ee83b06b607fb5; ?>
<?php unset($__componentOriginal7c1bf3a9346f208f66ee83b06b607fb5); ?>
<?php endif; ?>

                  <?php if (isset($component)) { $__componentOriginal0ff1ee8966084a5d418f848c5e125b44 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal0ff1ee8966084a5d418f848c5e125b44 = $attributes; } ?>
<?php $component = Illuminate\View\AnonymousComponent::resolve(['view' => 'components.auth-validation-errors','data' => ['class' => 'mb-4','errors' => $errors]] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('auth-validation-errors'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\Illuminate\View\AnonymousComponent::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes(['class' => 'mb-4','errors' => \Illuminate\View\Compilers\BladeCompiler::sanitizeComponentAttribute($errors)]); ?>
<?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal0ff1ee8966084a5d418f848c5e125b44)): ?>
<?php $attributes = $__attributesOriginal0ff1ee8966084a5d418f848c5e125b44; ?>
<?php unset($__attributesOriginal0ff1ee8966084a5d418f848c5e125b44); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal0ff1ee8966084a5d418f848c5e125b44)): ?>
<?php $component = $__componentOriginal0ff1ee8966084a5d418f848c5e125b44; ?>
<?php unset($__componentOriginal0ff1ee8966084a5d418f848c5e125b44); ?>
<?php endif; ?>
                  <form method="POST" action="<?php echo e(route('password.email')); ?>">
                     <?php echo e(csrf_field()); ?>

                     <div class="row">
                        <div class="col-lg-12">
                           <div class="floating-label form-group">
                              <label for="email" class="form-label"><?php echo e(__('message.email')); ?></label>
                              <input type="email" name="email" class="form-control" id="email" aria-describedby="email" placeholder=" ">
                           </div>
                        </div>
                     </div>
                     <button type="submit" class="btn btn-primary btn-block col-lg-12"><?php echo e(__('auth.email_password_reset_link')); ?></button>
                  </form>
               </div>
            </div>
            <div class="sign-bg sign-bg-right">
               
            </div>
         </div>
      </div>
   </section>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal69dc84650370d1d4dc1b42d016d7226b)): ?>
<?php $attributes = $__attributesOriginal69dc84650370d1d4dc1b42d016d7226b; ?>
<?php unset($__attributesOriginal69dc84650370d1d4dc1b42d016d7226b); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal69dc84650370d1d4dc1b42d016d7226b)): ?>
<?php $component = $__componentOriginal69dc84650370d1d4dc1b42d016d7226b; ?>
<?php unset($__componentOriginal69dc84650370d1d4dc1b42d016d7226b); ?>
<?php endif; ?>
<?php /**PATH /var/www/testapp/resources/views/auth/forgot-password.blade.php ENDPATH**/ ?>