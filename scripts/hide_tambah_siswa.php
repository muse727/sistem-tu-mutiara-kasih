<?php
$file = '/var/www/sistem-tu/resources/views/students/index.blade.php';
$content = file_get_contents($file);

// Replace standard Add/Import buttons wrapper
$searchButtons = '<div class="flex gap-2 w-full sm:w-auto">';
$replaceButtons = '@if(Auth::user()->role === \'admin\' || Auth::user()->role === \'tu\')' . "\n" . '            <div class="flex gap-2 w-full sm:w-auto">';

$searchEndButtons = '</button>' . "\n" . '            </div>' . "\n" . '        </div>';
$replaceEndButtons = '</button>' . "\n" . '            </div>' . "\n" . '            @endif' . "\n" . '        </div>';

$content = str_replace($searchButtons, $replaceButtons, $content);
$content = str_replace($searchEndButtons, $replaceEndButtons, $content);

// For empty state button
$searchEmpty = '<td colspan="9" class="px-4 py-12 text-center text-gray-500">' . "\n" . '                                <div class="flex flex-col items-center justify-center">' . "\n" . '                                    <svg class="w-12 h-12 text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">' . "\n" . '                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>' . "\n" . '                                    </svg>' . "\n" . '                                    <p class="text-sm font-medium">Belum ada data siswa</p>' . "\n" . '                                    <p class="text-xs text-gray-400 mt-1 mb-4">Mulai dengan menambahkan data siswa baru</p>' . "\n" . '                                    <button onclick="openModal(\'modalCreate\')" class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-xl text-sm font-medium transition">';

$replaceEmpty = '<td colspan="9" class="px-4 py-12 text-center text-gray-500">' . "\n" . '                                <div class="flex flex-col items-center justify-center">' . "\n" . '                                    <svg class="w-12 h-12 text-gray-300 mb-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">' . "\n" . '                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z"></path>' . "\n" . '                                    </svg>' . "\n" . '                                    <p class="text-sm font-medium">Belum ada data siswa</p>' . "\n" . '                                    @if(Auth::user()->role === \'admin\' || Auth::user()->role === \'tu\')' . "\n" . '                                    <p class="text-xs text-gray-400 mt-1 mb-4">Mulai dengan menambahkan data siswa baru</p>' . "\n" . '                                    <button onclick="openModal(\'modalCreate\')" class="inline-flex items-center gap-2 bg-blue-600 hover:bg-blue-700 text-white px-4 py-2 rounded-xl text-sm font-medium transition">';

$searchEndEmpty = 'Tambah Siswa Pertama' . "\n" . '                                </button>' . "\n" . '                            </td>';
$replaceEndEmpty = 'Tambah Siswa Pertama' . "\n" . '                                </button>' . "\n" . '                                @endif' . "\n" . '                            </td>';

$content = str_replace($searchEmpty, $replaceEmpty, $content);
$content = str_replace($searchEndEmpty, $replaceEndEmpty, $content);

file_put_contents($file, $content);
echo "Tambah Siswa UI hidden for staf.\n";
