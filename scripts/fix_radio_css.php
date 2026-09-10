<?php
$file = '/var/www/sistem-tu/resources/views/bills/create.blade.php';
$content = file_get_contents($file);

// 1. Tambahkan block CSS khusus di atas form
$css = <<<CSS
            <style>
                /* Styling khusus radio button biar nyala tanpa nunggu build Tailwind */
                .radio-card-input:checked + .radio-card {
                    border-color: #F56C3E !important;
                    background-color: #fff7ed !important;
                    box-shadow: 0 0 0 2px rgba(245, 108, 62, 0.2) !important;
                }
                .radio-card-input:checked + .radio-card .indicator {
                    border: 5px solid #F56C3E !important;
                    background-color: white !important;
                }
            </style>
            <form action="{{ route('bills.store') }}"
CSS;

$content = str_replace('<form action="{{ route(\'bills.store\') }}"', $css, $content);

// 2. Bersihkan class ruwet di input dan div
$content = str_replace('class="peer sr-only"', 'class="radio-card-input sr-only"', $content);

$searchDiv = 'class="p-4 bg-white border border-gray-200 rounded-xl hover:shadow-md transition peer-checked:border-[#F56C3E] peer-checked:ring-1 peer-checked:ring-[#F56C3E] peer-checked:bg-orange-50 peer-checked:[&_.indicator]:border-[#F56C3E] peer-checked:[&_.indicator]:bg-white peer-checked:[&_.indicator]:border-[5px]"';
$replaceDiv = 'class="radio-card p-4 bg-white border border-gray-200 rounded-xl hover:shadow-md transition"';
$content = str_replace($searchDiv, $replaceDiv, $content);

file_put_contents($file, $content);
