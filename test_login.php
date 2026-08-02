<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "Laravel version: " . app()->version() . "\n";
echo "Default guard: " . config('auth.defaults.guard') . "\n";

// Simulate login request
$request = \Illuminate\Http\Request::create('/api/login', 'POST', [
    'email' => 'test@test.com',
    'password' => 'Password123',
    'user_type' => 'user',
]);
app()->instance('request', $request);

$result = \Illuminate\Support\Facades\Auth::attempt([
    'email' => 'test@test.com',
    'password' => 'Password123',
    'user_type' => 'user',
]);
echo "Auth::attempt result: " . ($result ? 'TRUE' : 'FALSE') . "\n";

if ($result) {
    $user = \Illuminate\Support\Facades\Auth::user();
    echo "Logged in user: {$user->email}\n";
}
