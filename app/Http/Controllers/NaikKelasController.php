<?php

namespace App\Http\Controllers;

use App\Models\Kelas;
use App\Models\Setting;
use App\Models\Student;
use App\Models\StudentRombel;
use App\Models\StudentStatusLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class NaikKelasController extends Controller
{
    // Status yang tidak boleh diproses naik kelas
    const SKIP_STATUSES = ['keluar', 'graduated', 'alumni', 'pindah_keluar'];

    /**
     * Halaman utama — tampilkan mapping otomatis + preview siswa
     */
    public function index()
    {
        // Ambil semua kelas aktif beserta jumlah siswa aktif
        $kelasAktif = Kelas::aktif()
            ->withCount(['activeStudents'])
            ->orderBy('tingkat')
            ->orderBy('nama_kelas')
            ->get();

        // Semua kelas aktif untuk dropdown tujuan
        $semuaKelas = Kelas::aktif()->orderBy('tingkat')->orderBy('nama_kelas')->get();

        // Nama jenjang sekolah untuk ditampilkan di header view
        // school_settings (bukan settings) — sesuai struktur DB
        $jenjang = DB::table('school_settings')->where('key', 'jenjang')->value('value')
            ?? DB::table('school_settings')->where('key', 'school_name')->value('value')
            ?? 'Sekolah';

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
    }

    /**
     * Eksekusi naik kelas massal — otomatis berdasarkan tingkat
     */
    public function eksekusi(Request $request)
    {
        $request->validate([
            'catatan' => 'nullable|string|max:500',
        ]);

        $catatanTambahan = $request->catatan ?? '';
        $userId          = Auth::id();
        $tingkatMax      = Kelas::tingkatMaksimal();
        $totalNaik       = 0;
        $totalLulus      = 0;
        $totalDilewati   = 0;

        DB::transaction(function () use (
            $catatanTambahan, $userId, $tingkatMax,
            &$totalNaik, &$totalLulus, &$totalDilewati
        ) {
            // Ambil semua kelas aktif yang punya siswa aktif
            $kelasAktif = Kelas::aktif()
                ->withCount(['activeStudents'])
                ->having('active_students_count', '>', 0)
                ->orderBy('tingkat', 'desc')
                ->get();

            foreach ($kelasAktif as $kelas) {
                $isTingkatAkhir = $kelas->tingkat >= $tingkatMax;

                // Cari kelas tujuan untuk kelas non-tingkat-akhir
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
                }

                // Ambil semua siswa aktif di kelas ini
                $siswaList = Student::where('kelas_id', $kelas->id)
                    ->whereNotIn('status', self::SKIP_STATUSES)
                    ->get();

                foreach ($siswaList as $siswa) {
                    if ($isTingkatAkhir) {
                        // ── Tingkat akhir → Graduated ────────────────────────
                        $catatan = "Lulus dari {$kelas->nama_kelas}";
                        if ($catatanTambahan) $catatan .= " — {$catatanTambahan}";

                        $statusLama = $siswa->status;

                        $siswa->update([
                            'status'            => 'graduated',
                            'kelas_id'          => null,
                            'status_changed_at' => now(),
                            'status_changed_by' => $userId,
                        ]);

                        // Tandai tanggal_keluar di semua rombel aktif siswa yang lulus
                        \App\Models\StudentRombel::where('student_id', $siswa->id)
                            ->whereNull('tanggal_keluar')
                            ->update(['tanggal_keluar' => now()]);

                        StudentStatusLog::create([
                            'student_id'  => $siswa->id,
                            'status_lama' => $statusLama,
                            'status_baru' => 'graduated',
                            'catatan'     => $catatan,
                            'diubah_oleh' => $userId,
                        ]);

                        $totalLulus++;

                    } elseif ($kelasTujuan) {
                        // ── Naik ke kelas berikutnya ─────────────────────────
                        $catatan = "Naik kelas dari {$kelas->nama_kelas} ke {$kelasTujuan->nama_kelas}";
                        if ($catatanTambahan) $catatan .= " — {$catatanTambahan}";

                        $siswa->update([
                            'kelas_id'          => $kelasTujuan->id,
                            'status_changed_at' => now(),
                            'status_changed_by' => $userId,
                        ]);

                        // Tutup rombel lama siswa (set tanggal_keluar)
                        // agar jumlah_siswa di rombel lama berkurang
                        StudentRombel::where('student_id', $siswa->id)
                            ->whereNull('tanggal_keluar')
                            ->update(['tanggal_keluar' => now()]);

                        StudentStatusLog::create([
                            'student_id'  => $siswa->id,
                            'status_lama' => 'active',  // status tidak berubah
                            'status_baru' => 'active',  // status tidak berubah
                            'catatan'     => $catatan,
                            'diubah_oleh' => $userId,
                        ]);

                        $totalNaik++;

                    } else {
                        // ── Kelas tujuan tidak ditemukan → skip, catat ───────
                        $totalDilewati++;
                    }
                }
            }
        });

        $msg = [];
        if ($totalNaik > 0)     $msg[] = "{$totalNaik} siswa berhasil naik kelas";
        if ($totalLulus > 0)    $msg[] = "{$totalLulus} siswa dinyatakan lulus";
        if ($totalDilewati > 0) $msg[] = "{$totalDilewati} siswa dilewati (kelas tujuan tidak ditemukan)";

        if (empty($msg)) {
            return redirect()->route('naik-kelas.index')
                ->with('error', 'Tidak ada siswa yang diproses.');
        }

        return redirect()->route('naik-kelas.index')
            ->with('success', implode(', ', $msg) . '.');
    }
}
