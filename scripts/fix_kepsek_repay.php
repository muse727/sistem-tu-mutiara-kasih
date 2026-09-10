<?php
$content = file_get_contents('/var/www/sistem-tu/routes/web.php');

$search = "        // D. Laporan & Riwayat — admin,tu,kepala_sekolah\n        Route::middleware('role:admin,tu,kepala_sekolah')->controller(PosReportController::class)->prefix('history')->name('history.')->group(function () {\n            Route::get('/', 'index')->name('index');\n            Route::post('/{id}/repay', 'repay')->name('repay');\n        });";

$replace = "        // D. Laporan & Riwayat — admin,tu,kepala_sekolah\n        Route::middleware('role:admin,tu,kepala_sekolah')->controller(PosReportController::class)->prefix('history')->name('history.')->group(function () {\n            Route::get('/', 'index')->name('index');\n        });\n\n        // Repay/Void Transaksi — admin, tu only\n        Route::middleware('role:admin,tu')->controller(PosReportController::class)->prefix('history')->name('history.')->group(function () {\n            Route::post('/{id}/repay', 'repay')->name('repay');\n        });";

$content = str_replace($search, $replace, $content);
file_put_contents('/var/www/sistem-tu/routes/web.php', $content);
echo "Kepsek repay route fixed.\n";
