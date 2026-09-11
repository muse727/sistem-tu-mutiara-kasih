<?php
$content = file_get_contents('/var/www/sistem-tu/app/Http/Controllers/NaikKelasController.php');
$search = <<<'SEARCH'
        return view('students.naik-kelas.index', compact(
            'kelasAktif', 'semuaKelas', 'jenjang'
        ));
SEARCH;

$replace = <<<'REPLACE'
        $tingkatMax = Kelas::tingkatMaksimal();
        $totalSiswa = 0;
        $totalLulus = 0;

        foreach ($kelasAktif as $kelas) {
            if ($kelas->tingkat >= $tingkatMax) {
                $totalLulus += $kelas->active_students_count;
            } else {
                $totalSiswa += $kelas->active_students_count;
            }
        }

        return view('students.naik-kelas.index', compact(
            'kelasAktif', 'semuaKelas', 'jenjang', 'totalSiswa', 'totalLulus'
        ));
REPLACE;

$content = str_replace($search, $replace, $content);
file_put_contents('/var/www/sistem-tu/app/Http/Controllers/NaikKelasController.php', $content);
echo "Fix applied.\n";
