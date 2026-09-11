<?php
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "Opcache reset. ";
}
$dir = __DIR__ . '/../storage/framework/views/';
$files = glob($dir . '*.php');
foreach ($files as $file) {
    if (is_file($file)) {
        @unlink($file);
    }
}
echo "Views cleared.";
