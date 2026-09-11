<?php
$file = '/var/www/sistem-tu/resources/views/students/index.blade.php';
$content = file_get_contents($file);

// Remove the stray @endif at line 40
$content = str_replace(
"                </button>\n            </div>\n            @endif\n        </div>",
"                </button>\n            </div>\n        </div>",
$content
);

// Wrap the entire button group with @if(Auth::user()->role === 'admin' || Auth::user()->role === 'tu')
$searchButtons = '            <div class="flex items-center gap-2">' . "\n" . '                <a href="{{ route(\'naik-kelas.index\') }}"';
$replaceButtons = '            @if(Auth::user()->role === \'admin\' || Auth::user()->role === \'tu\')' . "\n" . '            <div class="flex items-center gap-2">' . "\n" . '                <a href="{{ route(\'naik-kelas.index\') }}"';

$searchEndButtons = '                </button>' . "\n" . '            </div>' . "\n" . '        </div>';
$replaceEndButtons = '                </button>' . "\n" . '            </div>' . "\n" . '            @endif' . "\n" . '        </div>';

$content = str_replace($searchButtons, $replaceButtons, $content);
$content = str_replace($searchEndButtons, $replaceEndButtons, $content);

file_put_contents($file, $content);
echo "Blade template index fixed.\n";
