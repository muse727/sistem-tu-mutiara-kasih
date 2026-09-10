<?php
$content = file_get_contents('/var/www/sistem-tu/routes/web.php');

// 1. Remove 'staf' from student write operations (they shouldn't update status, etc)
$searchStudentWrite = "        // Write operations — admin, tu, staf\n        Route::middleware('role:admin,tu,staf')->group(function () {\n            Route::get('/create', [StudentController::class, 'create'])->name('create');";
$replaceStudentWrite = "        // Write operations — admin, tu only (Staf POS should not edit students)\n        Route::middleware('role:admin,tu')->group(function () {\n            Route::get('/create', [StudentController::class, 'create'])->name('create');";
$content = str_replace($searchStudentWrite, $replaceStudentWrite, $content);

file_put_contents('/var/www/sistem-tu/routes/web.php', $content);
echo "Student write roles updated.\n";
