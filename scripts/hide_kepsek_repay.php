<?php
$file = '/var/www/sistem-tu/resources/views/pos/history/index.blade.php';
$content = file_get_contents($file);

$search = "                                        @if(\$trx->payment_status == 'UNPAID')\n                                            <button onclick=\"confirmRepay(";
$replace = "                                        @if(\$trx->payment_status == 'UNPAID')\n                                            @if(Auth::user()->role === 'admin' || Auth::user()->role === 'tu')\n                                            <button onclick=\"confirmRepay(";

$searchEnd = "                                                Bayar\n                                            </button>\n                                        @endif";
$replaceEnd = "                                                Bayar\n                                            </button>\n                                            @endif\n                                        @endif";

$content = str_replace($search, $replace, $content);
$content = str_replace($searchEnd, $replaceEnd, $content);

file_put_contents($file, $content);
echo "Kepsek repay UI hidden.\n";
