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
         <div class="col-md-6">
               <div class="mm-login-shapes-logo d-flex align-items-center justify-content-between">
               </div>
            <div class="row justify-content-center mb-4">
               <div class="col-md-10">
                  <div class="card card-transparent shadow-none d-flex justify-content-center mb-0 auth-card">
                     <div class="card-body">
                        <div class="auth-logo ">
                           <a href="<?php echo e(route('dashboard')); ?>" class="navbar-brand d-flex align-items-center mb-5">
                              <img src="<?php echo e(getSingleMedia(appSettingData('get'),'site_logo',null)); ?>" class="img-fluid mode light-img rounded-normal light-logo site_logo_preview" alt="logo">                             
                              <img src="<?php echo e(getSingleMedia(appSettingData('get'),'site_dark_logo',null)); ?>" class="img-fluid mode dark-img rounded-normal darkmode-logo site_dark_logo_preview" alt="dark-logo">                              
                              
                           </a>
                        </div>
                        <h4 class="mb-4 text-right"><?php echo e(__('auth.login')); ?></h4>
                        <!-- <p class="text-center">Login to stay connected.</p> -->
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

                        <!-- Validation Errors -->
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
                        <form method="POST" action="<?php echo e(route('login')); ?>" data-toggle="validator">
                            <?php echo e(csrf_field()); ?>

                           <div class="row">
                              <div class="col-lg-12">
                                 <div class="form-group">
                                    <label for="email" class="form-label"><?php echo e(__('message.email')); ?></label>
                                    <input id="email" type="email" name="email"  value="<?php echo e(old('email')); ?>" class="form-control"  placeholder="admin@example.com" required autofocus>
                                 </div>
                              </div>
                              <div class="col-lg-12">
                                 <div class="form-group">
                                    <label for="password" class="form-label"><?php echo e(__('message.password')); ?></label>
                                    <input class="form-control" type="password" placeholder="********"  name="password" value="" required autocomplete="current-password">
                                 </div>
                              </div>
                              <div class="col-lg-6">
                                 <div class="form-check mb-3">
                                    <input type="checkbox" class="form-check-input" id="customCheck1" name="remember" <?php echo e(old('remember') ? 'checked' : ''); ?>>
                                    <label class="form-check-label" for="customCheck1"><?php echo e(__('auth.remember_me')); ?></label>
                                 </div>
                              </div>
                              <div class="col-lg-6">
                                 <a href="<?php echo e(route('password.request')); ?>"  class="float-end"><?php echo e(__('auth.forgot_password')); ?></a>
                              </div>
                           </div>
                           <div class="d-flex justify-content-center">
                              <button type="submit" class="btn btn-primary col-lg-12"><?php echo e(__('auth.login')); ?></button>
                           </div>
                        </form>
                     </div>
                  </div>
               </div>
            </div>
            <div class="sign-bg">
               
            </div>
         </div>
         <div class="col-md-6 d-md-block d-none bg-soft-warning p-0 mt-n1 vh-100 overflow-hidden">
            <img src="<?php echo e(asset('images/auth/01.png')); ?>" class="img-fluid gradient-main animated-scaleX" alt="images">
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
<?php /**PATH /var/www/testapp/resources/views/auth/login.blade.php ENDPATH**/ ?>