<?php
$file = '/var/www/sistem-tu/resources/views/bills/create.blade.php';
$content = file_get_contents($file);

$searchDiv = 'peer-checked:[&_.indicator]:border-[4px]';
$replaceDiv = 'peer-checked:[&_.indicator]:border-[5px]';

$content = str_replace($searchDiv, $replaceDiv, $content);
file_put_contents($file, $content);
