<?php
$path = '/var/www/sistem-tu/resources/views/siswa/auth/login.blade.php';
$content = file_get_contents($path);

// Replace the body tag to ensure vertical scrolling works perfectly
$content = str_replace(
    '<body class="min-h-screen flex items-center justify-center px-4 py-10 relative">',
    '<body class="min-h-screen relative overflow-x-hidden bg-[#F5F5F5]">' . "\n    " . '<div class="min-h-screen flex flex-col justify-center items-center px-4 py-10 w-full">',
    $content
);

// Add the closing div right before scripts
$content = str_replace(
    '<script>',
    '</div>' . "\n\n    " . '<script>',
    $content
);

file_put_contents($path, $content);
echo "Fixed scroll layout in $path\n";
