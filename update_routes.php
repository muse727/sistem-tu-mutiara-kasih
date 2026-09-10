<?php
$content = file_get_contents('/var/www/sistem-tu/routes/web.php');

// 1. Fix Student routes missing role protection
$searchStudent = "    Route::prefix('students')->name('students.')->group(function () {\n        Route::get('/', [StudentController::class, 'index'])->name('index');";
$replaceStudent = "    Route::prefix('students')->name('students.')->group(function () {\n        // Read operations - admin, tu, staf, kepala_sekolah\n        Route::middleware('role:admin,tu,staf,kepala_sekolah')->group(function () {\n            Route::get('/', [StudentController::class, 'index'])->name('index');\n        });";
$content = str_replace($searchStudent, $replaceStudent, $content);

$searchWildcard = "        // Wildcard routes last — after all static routes\n        Route::get('/{id}', [StudentController::class, 'show'])->name('show');\n        Route::get('/{id}/finance', [StudentController::class, 'show'])->name('finance');\n        Route::get('/{id}/status', [StudentController::class, 'formUbahStatus'])->name('ubah-status');\n    });";
$replaceWildcard = "        // Wildcard routes last — after all static routes\n        // Read operations - admin, tu, staf, kepala_sekolah\n        Route::middleware('role:admin,tu,staf,kepala_sekolah')->group(function () {\n            Route::get('/{id}', [StudentController::class, 'show'])->name('show');\n            Route::get('/{id}/finance', [StudentController::class, 'show'])->name('finance');\n            Route::get('/{id}/status', [StudentController::class, 'formUbahStatus'])->name('ubah-status');\n        });\n    });";
$content = str_replace($searchWildcard, $replaceWildcard, $content);

// 2. Extract PanduanController from ProfileController group
$searchPanduan = "    Route::controller(ProfileController::class)->group(function () {\n        Route::get('/panduan', [\App\Http\Controllers\PanduanController::class, 'index'])->name('panduan.index');\n        Route::get('/profile', 'edit')->name('profile.edit');";
$replacePanduan = "    Route::get('/panduan', [\App\Http\Controllers\PanduanController::class, 'index'])->name('panduan.index');\n\n    Route::controller(ProfileController::class)->group(function () {\n        Route::get('/profile', 'edit')->name('profile.edit');";
$content = str_replace($searchPanduan, $replacePanduan, $content);

file_put_contents('/var/www/sistem-tu/routes/web.php', $content);
echo "Routes updated.\n";
