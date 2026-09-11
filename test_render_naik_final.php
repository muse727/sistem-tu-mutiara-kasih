<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::capture();
$app->instance('request', $request);
$kernel->bootstrap();

use Illuminate\Support\Facades\Auth;

Auth::shouldReceive('user')->andReturn((object)['id' => 1, 'role' => 'admin']);
Auth::shouldReceive('check')->andReturn(true);

try {
    $response = app()->make(\App\Http\Controllers\NaikKelasController::class)->index();
    $html = $response->render();
    if (strpos($html, 'Tidak ada siswa aktif') !== false || strpos($html, 'Preview Mapping Otomatis') !== false) {
        echo "Render SUCCESS with expected content.";
    } else {
        echo "Render SUCCESS but missing expected content.";
    }
} catch (\Exception $e) {
    echo "ERROR: " . $e->getMessage() . " at " . $e->getFile() . ":" . $e->getLine() . "\n";
}
