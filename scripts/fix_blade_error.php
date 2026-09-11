<?php
$file = '/var/www/sistem-tu/resources/views/students/index.blade.php';
$content = file_get_contents($file);

// Find the stray @endif in the empty state and properly wrap the button
$search = "                                <button onclick=\"openModal('modalCreate')\"\n                                        class=\"mt-4 inline-flex items-center gap-2 bg-[#F56C3E] text-white px-5 py-2.5 rounded-xl hover:bg-[#e45322] transition-all duration-300 font-medium shadow-sm text-sm\">\n                                    <svg class=\"w-4 h-4\" fill=\"none\" stroke=\"currentColor\" viewBox=\"0 0 24 24\">\n                                        <path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M12 4v16m8-8H4\"></path>\n                                    </svg>\n                                    Tambah Siswa Pertama\n                                </button>\n                                @endif";

$replace = "                                @if(Auth::user()->role === 'admin' || Auth::user()->role === 'tu')\n                                <button onclick=\"openModal('modalCreate')\"\n                                        class=\"mt-4 inline-flex items-center gap-2 bg-[#F56C3E] text-white px-5 py-2.5 rounded-xl hover:bg-[#e45322] transition-all duration-300 font-medium shadow-sm text-sm\">\n                                    <svg class=\"w-4 h-4\" fill=\"none\" stroke=\"currentColor\" viewBox=\"0 0 24 24\">\n                                        <path stroke-linecap=\"round\" stroke-linejoin=\"round\" stroke-width=\"2\" d=\"M12 4v16m8-8H4\"></path>\n                                    </svg>\n                                    Tambah Siswa Pertama\n                                </button>\n                                @endif";

$content = str_replace($search, $replace, $content);
file_put_contents($file, $content);
echo "Blade error fixed.\n";
