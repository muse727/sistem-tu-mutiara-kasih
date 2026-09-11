<?php
$files = glob('/var/www/sistem-tu/storage/framework/views/*.php');
foreach ($files as $file) {
    if (is_file($file)) {
        unlink($file);
    }
}
echo "Cleared views.\n";
