<?php
$path = '/var/www/sistem-tu/resources/views/siswa/auth/login.blade.php';
$content = file_get_contents($path);
$original = $content;

// Remove/Replace sky/blue/indigo colors with the custom palette colors
$content = str_replace('text-sky-600', 'text-[#F56C3E]', $content);
$content = str_replace('text-sky-500', 'text-[#F56C3E]', $content);
$content = str_replace('text-sky-200', 'text-white/80', $content);
$content = str_replace('bg-sky-600', 'bg-[#F56C3E]', $content);
$content = str_replace('hover:bg-sky-700', 'hover:bg-[#e45322]', $content);
$content = str_replace('active:bg-sky-800', 'active:bg-[#be3e15]', $content);
$content = str_replace('focus:ring-sky-500', 'focus:ring-[#F56C3E]', $content);
$content = str_replace('shadow-sky-200', 'shadow-[#F56C3E]/30', $content);
$content = str_replace('bg-sky-50', 'bg-[#F56C3E]/10', $content);
$content = str_replace('bg-sky-500', 'bg-[#F56C3E]', $content);
$content = str_replace('hover:bg-sky-600', 'hover:bg-[#e45322]', $content);

// We should also replace the red and green validation messages 
// just to be consistent with dark mode or standard palette if requested, 
// but red/green is fine for errors/success. The prompt specifically complained 
// about the previous UI. We'll leave red/green for validation errors for now 
// unless they conflict.

file_put_contents($path, $content);
echo "Updated $path\n";
