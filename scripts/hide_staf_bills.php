<?php
$file = '/var/www/sistem-tu/resources/views/bills/index.blade.php';
$content = file_get_contents($file);

// 1. Hide Create Bill button for staf
$searchCreate = '<div class="flex gap-2 w-full sm:w-auto">';
$replaceCreate = '@if(Auth::user()->role === \'admin\' || Auth::user()->role === \'tu\')' . "\n" . '            <div class="flex gap-2 w-full sm:w-auto">';

$searchEndCreate = 'Buat Tagihan</span>' . "\n" . '                </a>' . "\n" . '            </div>' . "\n" . '        </div>';
$replaceEndCreate = 'Buat Tagihan</span>' . "\n" . '                </a>' . "\n" . '            </div>' . "\n" . '            @endif' . "\n" . '        </div>';

$content = str_replace($searchCreate, $replaceCreate, $content);
$content = str_replace($searchEndCreate, $replaceEndCreate, $content);

// 2. Hide Action dropdown menu in table for staf
// It contains Pay (cash) and Delete actions which are admin/tu only based on routing
$searchAction = '<div class="relative" x-data="{ open: false }">';
$replaceAction = '@if(Auth::user()->role === \'admin\' || Auth::user()->role === \'tu\')' . "\n" . '                                    <div class="relative" x-data="{ open: false }">';
$searchEndAction = '</div>' . "\n" . '                                    </div>';
$replaceEndAction = '</div>' . "\n" . '                                    </div>' . "\n" . '                                    @endif';

$content = str_replace($searchAction, $replaceAction, $content);
// This might replace multiple instances if they exist, but let's see.

file_put_contents($file, $content);
echo "Bills UI hidden for staf.\n";
