<?php
$content = file_get_contents('/var/www/sistem-tu/routes/web.php');

// Move ubah-status form to admin,tu only
$searchWildcard = "        // Read operations - admin, tu, staf, kepala_sekolah\n        Route::middleware('role:admin,tu,staf,kepala_sekolah')->group(function () {\n            Route::get('/{id}', [StudentController::class, 'show'])->name('show');\n            Route::get('/{id}/finance', [StudentController::class, 'show'])->name('finance');\n            Route::get('/{id}/status', [StudentController::class, 'formUbahStatus'])->name('ubah-status');\n        });";

$replaceWildcard = "        // Read operations - admin, tu, staf, kepala_sekolah\n        Route::middleware('role:admin,tu,staf,kepala_sekolah')->group(function () {\n            Route::get('/{id}', [StudentController::class, 'show'])->name('show');\n            Route::get('/{id}/finance', [StudentController::class, 'show'])->name('finance');\n        });\n\n        // Ubah status form - admin, tu only\n        Route::middleware('role:admin,tu')->group(function () {\n            Route::get('/{id}/status', [StudentController::class, 'formUbahStatus'])->name('ubah-status');\n        });";

$content = str_replace($searchWildcard, $replaceWildcard, $content);
file_put_contents('/var/www/sistem-tu/routes/web.php', $content);
echo "Routes staf fixed.\n";
