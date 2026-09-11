<?php
$content = file_get_contents('/var/www/sistem-tu/resources/views/students/index.blade.php');
$ifs = substr_count($content, '@if');
$endifs = substr_count($content, '@endif');
echo "Ifs: $ifs, Endifs: $endifs\n";
