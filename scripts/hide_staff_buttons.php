<?php
$file = '/var/www/sistem-tu/resources/views/students/show.blade.php';
$content = file_get_contents($file);

// Hide edit buttons from staf role
$searchEdit = '<div class="mt-4 space-y-2">';
$replaceEdit = '<div class="mt-4 space-y-2">' . "\n" . '                        @if(Auth::user()->role === \'admin\' || Auth::user()->role === \'tu\')';
$content = str_replace($searchEdit, $replaceEdit, $content);

$searchEndEdit = '                        </form>' . "\n" . '                        @endif' . "\n" . '                    </div>';
$replaceEndEdit = '                        </form>' . "\n" . '                        @endif' . "\n" . '                        @endif' . "\n" . '                    </div>';
$content = str_replace($searchEndEdit, $replaceEndEdit, $content);

file_put_contents($file, $content);
echo "Staff buttons hidden.\n";
