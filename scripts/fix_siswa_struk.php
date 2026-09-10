<?php
$path = '/var/www/sistem-tu/resources/views/siswa/struk.blade.php';
$content = file_get_contents($path);

// Top Gradient
$content = preg_replace('/bg-gradient-to-r from-sky-500 via-sky-600 to-blue-700/', 'bg-gradient-to-r from-[#F56C3E] to-[#e45322]', $content);
$content = str_replace('text-sky-200', 'text-white/80', $content);

// Small decorative gradient line at the top of the receipt card
$content = preg_replace('/bg-gradient-to-r from-sky-400 via-blue-500 to-sky-600/', 'bg-gradient-to-r from-[#F56C3E] to-[#e45322]', $content);

// Download/Print button
$content = str_replace('bg-sky-600', 'bg-[#F56C3E]', $content);
$content = str_replace('hover:bg-sky-700', 'hover:bg-[#e45322]', $content);
$content = str_replace('active:bg-sky-800', 'active:bg-[#be3e15]', $content);
$content = str_replace('shadow-sky-200', 'shadow-[#F56C3E]/30', $content);

file_put_contents($path, $content);
echo "Updated $path\n";
