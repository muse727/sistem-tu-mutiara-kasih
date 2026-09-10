<?php
$directory = new RecursiveDirectoryIterator('/var/www/sistem-tu/resources/views');
$iterator = new RecursiveIteratorIterator($directory);
$files = new RegexIterator($iterator, '/^.+\.blade\.php$/i', RecursiveRegexIterator::GET_MATCH);

$count = 0;
foreach ($files as $file) {
    $path = $file[0];
    $content = file_get_contents($path);
    $original = $content;

    // Badges & Statuses
    // Success / Active
    $content = preg_replace('/bg-(emerald|green|teal)-50 text-(emerald|green|teal)-(600|700|800) border border-(emerald|green|teal)-(100|200)/', 'bg-[#F56C3E] text-white shadow-sm', $content);
    $content = preg_replace('/bg-(emerald|green|teal)-100 text-(emerald|green|teal)-(600|700|800) border border-(emerald|green|teal)-(200|300)/', 'bg-[#F56C3E] text-white shadow-sm', $content);
    $content = preg_replace('/bg-(emerald|green|teal)-(50|100) text-(emerald|green|teal)-(600|700)/', 'bg-[#F56C3E] text-white shadow-sm', $content);
    
    // Danger / Inactive
    $content = preg_replace('/bg-(red|rose)-50 text-(red|rose)-(600|700|800) border border-(red|rose)-(100|200)/', 'bg-[#171717] text-white shadow-sm', $content);
    $content = preg_replace('/bg-(red|rose)-100 text-(red|rose)-(600|700|800) border border-(red|rose)-(200|300)/', 'bg-[#171717] text-white shadow-sm', $content);
    $content = preg_replace('/bg-(red|rose)-(50|100) text-(red|rose)-(600|700)/', 'bg-[#171717] text-white shadow-sm', $content);
    
    // Warning / Pending
    $content = preg_replace('/bg-(yellow|orange|amber)-50 text-(yellow|orange|amber)-(600|700|800) border border-(yellow|orange|amber)-(100|200)/', 'bg-[#EDEDED] text-[#171717] shadow-sm', $content);
    $content = preg_replace('/bg-(yellow|orange|amber)-100 text-(yellow|orange|amber)-(600|700|800) border border-(yellow|orange|amber)-(200|300)/', 'bg-[#EDEDED] text-[#171717] shadow-sm', $content);
    $content = preg_replace('/bg-(yellow|orange|amber)-(50|100) text-(yellow|orange|amber)-(600|700)/', 'bg-[#EDEDED] text-[#171717] shadow-sm', $content);
    
    // Info / Neutral (Blue, Sky, Indigo, Gray)
    $content = preg_replace('/bg-(blue|sky|indigo|gray)-50 text-(blue|sky|indigo|gray)-(600|700|800) border border-(blue|sky|indigo|gray)-(100|200)/', 'bg-[#EDEDED] text-[#171717] shadow-sm', $content);
    $content = preg_replace('/bg-(blue|sky|indigo|gray)-100 text-(blue|sky|indigo|gray)-(600|700|800) border border-(blue|sky|indigo|gray)-(200|300)/', 'bg-[#EDEDED] text-[#171717] shadow-sm', $content);
    $content = preg_replace('/bg-(blue|sky|indigo|gray)-(50|100) text-(blue|sky|indigo|gray)-(600|700)/', 'bg-[#EDEDED] text-[#171717] shadow-sm', $content);

    // Specific Buttons & Anchors
    $content = preg_replace('/bg-sky-50 hover:bg-sky-100 text-sky-700( border border-sky-200)?/', 'bg-[#171717] hover:bg-[#F56C3E] text-white', $content);
    $content = preg_replace('/bg-gray-50 hover:bg-sky-50 border border-gray-200/', 'bg-[#EDEDED] hover:bg-[#F56C3E] text-[#171717] hover:text-white', $content);
    $content = preg_replace('/text-gray-600 hover:text-\[#F56C3E\] bg-gray-50 hover:bg-sky-50 border border-gray-200/', 'text-[#171717] hover:text-white bg-[#EDEDED] hover:bg-[#F56C3E] border border-[#EDEDED]', $content);
    
    // Icons & Card Stats Headers in index pages
    $content = preg_replace('/text-(blue|emerald|green|red|yellow|orange|sky)-600 mt-1/', 'text-[#171717] mt-1', $content);
    $content = preg_replace('/bg-(blue|emerald|green|red|yellow|orange|sky)-500\/10/', 'bg-[#F56C3E]/10', $content);
    $content = preg_replace('/text-(blue|emerald|green|red|yellow|orange|sky)-500/', 'text-[#F56C3E]', $content);

    if ($original !== $content) {
        file_put_contents($path, $content);
        echo "Updated $path\n";
        $count++;
    }
}
echo "Total modified: $count files.\n";
