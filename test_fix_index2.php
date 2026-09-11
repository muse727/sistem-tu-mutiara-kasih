<?php
$content = file_get_contents('/var/www/sistem-tu/app/Http/Controllers/NaikKelasController.php');
$search = <<<'SEARCH'
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
SEARCH;

$replace = <<<'REPLACE'
        $tingkatMax = Kelas::tingkatMaksimal();
        $totalSiswa = 0;
        $totalLulus = 0;
        $mappings   = collect();

        foreach ($kelasAktif as $kelas) {
            $isTingkatAkhir = $kelas->tingkat >= $tingkatMax;
            $kelasTujuan = null;

            if (!$isTingkatAkhir) {
                $kelasTujuan = Kelas::aktif()
                    ->where('tingkat', $kelas->tingkat + 1)
                    ->where(function ($q) use ($kelas) {
                        if ($kelas->jurusan) {
                            $q->where('jurusan', $kelas->jurusan);
                        } else {
                            $q->whereNull('jurusan');
                        }
                    })
                    ->first();
                $totalSiswa += $kelas->active_students_count;
            } else {
                $totalLulus += $kelas->active_students_count;
            }

            $mappings->push([
                'kelas_asal'         => $kelas,
                'is_tingkat_akhir'   => $isTingkatAkhir,
                'kelas_tujuan'       => $kelasTujuan,
                'jumlah_siswa_aktif' => $kelas->active_students_count,
            ]);
        }

        return view('students.naik-kelas.index', compact(
            'kelasAktif', 'semuaKelas', 'jenjang', 'totalSiswa', 'totalLulus', 'mappings'
        ));
REPLACE;

$content = str_replace($search, $replace, $content);
file_put_contents('/var/www/sistem-tu/app/Http/Controllers/NaikKelasController.php', $content);
echo "Fix mappings applied.\n";
