<?php
$file = '/var/www/sistem-tu/resources/views/students/index.blade.php';
$content = file_get_contents($file);

// Hide edit/hapus button in the table row
$searchActionButtons = '<button onclick="fetchAndEditStudent({{ $student->id }})"';
$replaceActionButtons = '@if(Auth::user()->role === \'admin\' || Auth::user()->role === \'tu\')' . "\n" . '                                    <button onclick="fetchAndEditStudent({{ $student->id }})"';

$searchEndActionButtons = 'Hapus' . "\n" . '                                    </button>' . "\n" . '                                </div>';
$replaceEndActionButtons = 'Hapus' . "\n" . '                                    </button>' . "\n" . '                                    @endif' . "\n" . '                                </div>';

$content = str_replace($searchActionButtons, $replaceActionButtons, $content);
$content = str_replace($searchEndActionButtons, $replaceEndActionButtons, $content);

// Also fix the empty state (it might be duplicated or broken if we run scripts multiple times)
// Let's check if there is an orphan `@endif` from my previous run.
$content = preg_replace('/Tambah Siswa Pertama\s*<\/button>\s*@endif\s*@endif/m', "Tambah Siswa Pertama\n                                </button>\n                                @endif", $content);

file_put_contents($file, $content);
echo "Student action buttons hidden for staf.\n";
