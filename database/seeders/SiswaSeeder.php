<?php

namespace Database\Seeders;

use App\Models\Kelas;
use App\Models\Rombel;
use App\Models\Student;
use App\Models\TahunAjaran;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * Seed data siswa contoh (nama acak) sesuai struktur database:
 * tahun_ajaran → kelas → rombels → students → student_rombels.
 *
 * Idempoten: aman dijalankan ulang (firstOrCreate by nis / nama).
 * Password login siswa mengikuti aturan sistem: tanggal lahir (ddmmyy),
 * karena custom_password dibiarkan null.
 */
class SiswaSeeder extends Seeder
{
    public function run(): void
    {
        // 1. Tahun ajaran aktif
        $ta = TahunAjaran::firstOrCreate(
            ['nama' => '2025/2026'],
            [
                'tanggal_mulai'   => '2025-07-14',
                'tanggal_selesai' => '2026-06-30',
                'is_aktif'        => true,
            ]
        );
        $ta->setAktif();

        // 2. Kelas (jenjang SMA → tingkat 10–12)
        $kelasData = [
            ['nama_kelas' => 'X-A',  'tingkat' => 10, 'jurusan' => null, 'wali_kelas' => 'Nurul Hidayah, S.Pd.'],
            ['nama_kelas' => 'X-B',  'tingkat' => 10, 'jurusan' => null, 'wali_kelas' => 'Bambang Sutrisno, S.Pd.'],
            ['nama_kelas' => 'XI-A', 'tingkat' => 11, 'jurusan' => null, 'wali_kelas' => 'Siti Rahmawati, M.Pd.'],
            ['nama_kelas' => 'XI-B', 'tingkat' => 11, 'jurusan' => null, 'wali_kelas' => 'Agus Wibowo, S.Pd.'],
            ['nama_kelas' => 'XII-A','tingkat' => 12, 'jurusan' => null, 'wali_kelas' => 'Dewi Lestari, M.Pd.'],
            ['nama_kelas' => 'XII-B','tingkat' => 12, 'jurusan' => null, 'wali_kelas' => 'Hendra Gunawan, S.Pd.'],
        ];

        $kelasMap = [];
        foreach ($kelasData as $k) {
            $kelasMap[$k['nama_kelas']] = Kelas::firstOrCreate(
                ['nama_kelas' => $k['nama_kelas']],
                [
                    'tingkat'    => $k['tingkat'],
                    'jurusan'    => $k['jurusan'],
                    'wali_kelas' => $k['wali_kelas'],
                    'is_aktif'   => true,
                ]
            );
        }

        // 3. Rombel per kelas untuk tahun ajaran aktif
        $rombelMap = [];
        foreach ($kelasMap as $nama => $kelas) {
            $rombelMap[$nama] = Rombel::firstOrCreate(
                [
                    'tahun_ajaran_id' => $ta->id,
                    'kelas_id'        => $kelas->id,
                ],
                [
                    'nama_rombel' => $nama,
                    'wali_kelas'  => $kelas->wali_kelas,
                    'is_aktif'    => true,
                ]
            );
        }

        // 4. Siswa per kelas (nama acak, natural Indonesia)
        $siswa = [
            'X-A' => [
                ['Aditya Pratama', 'L', '2009-03-14'],
                ['Bunga Cahyani', 'P', '2010-01-22'],
                ['Candra Wijaya', 'L', '2009-11-05'],
                ['Dinda Ayu Permata', 'P', '2010-05-30'],
                ['Eko Saputra', 'L', '2009-07-19'],
                ['Fitri Handayani', 'P', '2010-02-11'],
                ['Gilang Ramadhan', 'L', '2009-09-08'],
                ['Hana Salsabila', 'P', '2010-04-27'],
                ['Ilham Maulana', 'L', '2009-12-02'],
                ['Jihan Aulia', 'P', '2010-06-16'],
            ],
            'X-B' => [
                ['Kevin Ardiansyah', 'L', '2009-08-21'],
                ['Laila Nur Azizah', 'P', '2010-03-09'],
                ['Muhammad Rizki', 'L', '2009-05-17'],
                ['Nabila Zahra', 'P', '2010-07-25'],
                ['Oktavian Yusuf', 'L', '2009-10-13'],
                ['Putri Melati', 'P', '2010-01-04'],
                ['Qori Ananda', 'P', '2010-08-30'],
                ['Rafi Abiyyu', 'L', '2009-04-06'],
            ],
            'XI-A' => [
                ['Salma Nabila', 'P', '2008-09-12'],
                ['Taufik Hidayat', 'L', '2008-02-28'],
                ['Umar Abdillah', 'L', '2008-06-08'],
                ['Vina Anggraini', 'P', '2008-11-23'],
                ['Wahyu Nugroho', 'L', '2008-03-15'],
                ['Xena Kirana', 'P', '2008-07-01'],
                ['Yoga Prasetyo', 'L', '2008-12-19'],
                ['Zahwa Aqila', 'P', '2008-05-26'],
            ],
            'XI-B' => [
                ['Aisyah Putri', 'P', '2008-04-10'],
                ['Bagas Setiawan', 'L', '2008-08-24'],
                ['Citra Dewanti', 'P', '2008-01-30'],
                ['Danu Kusuma', 'L', '2008-10-07'],
                ['Elsa Wulandari', 'P', '2008-06-13'],
                ['Fajar Nurdin', 'L', '2008-02-02'],
            ],
            'XII-A' => [
                ['Gita Savitri', 'P', '2007-09-18'],
                ['Hafiz Alfarizi', 'L', '2007-03-27'],
                ['Indah Permatasari', 'P', '2007-12-05'],
                ['Joko Susilo', 'L', '2007-07-22'],
                ['Kirana Maheswari', 'P', '2007-04-14'],
                ['Lukman Hakim', 'L', '2007-10-29'],
            ],
            'XII-B' => [
                ['Maya Puspita', 'P', '2007-06-09'],
                ['Naufal Rizky', 'L', '2007-01-16'],
                ['Olivia Sari', 'P', '2007-11-03'],
                ['Panji Prakoso', 'L', '2007-05-20'],
                ['Rani Kusnadi', 'P', '2007-08-12'],
                ['Sultan Maher', 'L', '2007-02-25'],
            ],
        ];

        $agamaList = ['Islam', 'Kristen', 'Katolik', 'Hindu'];
        $tahunMasukByTingkat = [10 => 2025, 11 => 2024, 12 => 2023];
        $counter = 0;
        $created = 0;

        foreach ($siswa as $namaKelas => $list) {
            $kelas = $kelasMap[$namaKelas];
            $rombel = $rombelMap[$namaKelas];
            $tingkat = $kelas->tingkat;

            foreach ($list as $i => [$nama, $gender, $lahir]) {
                $counter++;
                $nis = sprintf('2025%04d', $counter);

                $student = Student::firstOrCreate(
                    ['nis' => $nis],
                    [
                        'name'         => $nama,
                        'gender'       => $gender,
                        'birth_place'  => 'Jakarta',
                        'birth_date'   => $lahir,
                        'agama'        => $agamaList[$counter % 4],
                        'tahun_masuk'  => $tahunMasukByTingkat[$tingkat],
                        'kelas_id'     => $kelas->id,
                        'address'      => 'Jl. Contoh No. '.$counter.', Jakarta',
                        'parent_phone' => '0812'.sprintf('%08d', 30000000 + $counter),
                        'status'       => 'active',
                    ]
                );

                if ($student->wasRecentlyCreated) {
                    $created++;
                }

                DB::table('student_rombels')->updateOrInsert(
                    [
                        'student_id'      => $student->id,
                        'tahun_ajaran_id' => $ta->id,
                    ],
                    [
                        'rombel_id'  => $rombel->id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]
                );
            }
        }

        $this->command->info("Siswa baru dibuat: {$created}. Total siswa aktif: ".Student::where('status', 'active')->count());
    }
}
