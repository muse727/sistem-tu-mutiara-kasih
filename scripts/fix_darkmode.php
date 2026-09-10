<?php
$directory = new RecursiveDirectoryIterator('/var/www/sistem-tu/resources/views');
$iterator = new RecursiveIteratorIterator($directory);
$files = new RegexIterator($iterator, '/^.+\.blade\.php$/i', RecursiveRegexIterator::GET_MATCH);

$count = 0;
foreach ($files as $file) {
    $path = $file[0];
    $content = file_get_contents($path);
    $original = $content;

    // 1. Fix bg-[#EDEDED] text-[#171717] badges -> bg-white text-[#171717] border border-[#EDEDED]
    $content = str_replace('bg-[#EDEDED] text-[#171717] shadow-sm', 'bg-white text-[#171717] border border-[#EDEDED] shadow-sm', $content);
    
    // 2. Fix the Detail buttons and similar with bg-sky-50
    $content = preg_replace('/bg-sky-50 hover:bg-\[#EDEDED\] text-\[#171717\]( shadow-sm)?/', 'bg-white hover:bg-gray-50 text-[#171717] border border-gray-200 shadow-sm', $content);
    $content = preg_replace('/bg-sky-50 hover:bg-gray-50 text-\[#171717\]/', 'bg-white hover:bg-gray-50 text-[#171717] border border-gray-200', $content);
    
    // 3. Fix NIS/NISN badges (bg-gray-100 text-gray-600)
    $content = preg_replace('/bg-gray-100 text-gray-600/', 'bg-white text-gray-700 border border-gray-200', $content);
    $content = preg_replace('/bg-gray-50 text-gray-600/', 'bg-white text-gray-700 border border-gray-200', $content);
    
    // 4. Fix Kelas badges (bg-gradient-to-r from-gray-50 to-gray-100 text-gray-700)
    $content = str_replace('bg-gradient-to-r from-gray-50 to-gray-100', 'bg-white', $content);
    
    // 5. Any leftover bg-[#EDEDED] that is used for badges
    $content = str_replace('bg-[#EDEDED] text-[#171717]', 'bg-white text-[#171717] border border-gray-200', $content);

    // 6. Any bg-blue-50 text-blue-600 leftovers
    $content = preg_replace('/bg-blue-50 text-blue-600/', 'bg-white text-[#171717] border border-gray-200', $content);

    // 7. Any border-blue-100, border-sky-200 etc on badges
    $content = preg_replace('/border-blue-100/', 'border-gray-200', $content);
    $content = preg_replace('/border-sky-200/', 'border-gray-200', $content);
    
    // 8. Fix the specific "Tidak Aktif" badging if needed. Wait, in the code snippet: 
    // <p class="text-sm text-red-800 font-medium">Tidak Aktif</p> -> red-800 becomes #be3e15. If it's on white, force dark mode inverts white to black, and #be3e15 to something else.
    // The main issue is the badges.

    if ($original !== $content) {
        file_put_contents($path, $content);
        echo "Updated $path\n";
        $count++;
    }
}
echo "Total modified: $count files.\n";
