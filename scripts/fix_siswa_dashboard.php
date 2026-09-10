<?php
$path = '/var/www/sistem-tu/resources/views/siswa/dashboard.blade.php';
$content = file_get_contents($path);

// Convert all remaining sky/blue gradients and backgrounds to the brand's Orange palette.
// Instead of a gradient of sky-to-blue, we'll use solid #F56C3E or a subtle gradient of our oranges.

// Header Gradient
$content = preg_replace('/bg-gradient-to-r from-sky-500 via-sky-600 to-blue-700/', 'bg-gradient-to-r from-[#F56C3E] to-[#e45322]', $content);
$content = preg_replace('/bg-gradient-to-r from-sky-500 to-blue-600/', 'bg-gradient-to-r from-[#F56C3E] to-[#e45322]', $content);

// Subtext in headers (like NIS and Tata Usaha subtext)
$content = str_replace('text-sky-200', 'text-white/80', $content);
$content = str_replace('text-sky-300', 'text-white/90', $content);

// Tabs
$content = str_replace('border-sky-600', 'border-[#F56C3E]', $content);
$content = str_replace('text-sky-600', 'text-[#F56C3E]', $content);
$content = str_replace('bg-sky-50/50', 'bg-[#F56C3E]/10', $content);
$content = str_replace('bg-sky-50/60', 'bg-[#F56C3E]/10', $content);
$content = str_replace('bg-sky-50/40', 'bg-[#F56C3E]/10', $content);
$content = str_replace('active:bg-sky-100/60', 'active:bg-[#F56C3E]/20', $content);

// Primary Buttons & Checkboxes (bg-sky-600)
$content = str_replace('bg-sky-600', 'bg-[#F56C3E]', $content);
$content = str_replace('hover:bg-sky-700', 'hover:bg-[#e45322]', $content);
$content = str_replace('active:bg-sky-800', 'active:bg-[#be3e15]', $content);
$content = str_replace('shadow-sky-200', 'shadow-[#F56C3E]/30', $content);

// Disabled states & rings
$content = str_replace('disabled:bg-sky-300', 'disabled:bg-[#F56C3E]/50', $content);
$content = str_replace('focus:border-sky-400', 'focus:border-[#F56C3E]', $content);
$content = str_replace('focus:ring-sky-400', 'focus:ring-[#F56C3E]', $content);

// Secondary Backgrounds / Boxes (like the summary boxes or chat loading indicator)
$content = str_replace('bg-sky-50', 'bg-[#F56C3E]/10', $content);
$content = str_replace('bg-sky-500', 'bg-[#F56C3E]', $content);
$content = str_replace('hover:bg-sky-600', 'hover:bg-[#e45322]', $content);
$content = str_replace('text-sky-700', 'text-[#963316]', $content);
$content = str_replace('text-sky-400', 'text-[#F56C3E]', $content);
$content = str_replace('bg-sky-400', 'bg-[#F56C3E]', $content);
$content = str_replace('border-sky-500', 'border-[#F56C3E]', $content);

file_put_contents($path, $content);
echo "Updated $path\n";
