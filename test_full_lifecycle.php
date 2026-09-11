<?php
require __DIR__.'/vendor/autoload.php';
$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Http\Kernel::class);
$request = Illuminate\Http\Request::capture();
$app->instance('request', $request);
$kernel->bootstrap();

use App\Models\Kelas;
use App\Models\TahunAjaran;
use App\Models\Rombel;
use App\Models\Student;
use App\Models\StudentRombel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Session;

Auth::shouldReceive('id')->andReturn(1);
Auth::shouldReceive('user')->andReturn((object)['id' => 1, 'role' => 'admin']);
Auth::shouldReceive('check')->andReturn(true);
Session::start();

DB::beginTransaction();
try {
    echo "=== PERSIAPAN DATA ===\n";
    $ta1 = TahunAjaran::firstOrCreate(['nama' => '2025/2026'], ['is_aktif' => true, 'tanggal_mulai' => now(), 'tanggal_selesai' => now()->addYear()]);
    $ta2 = TahunAjaran::firstOrCreate(['nama' => '2026/2027'], ['is_aktif' => false, 'tanggal_mulai' => now()->addYear(), 'tanggal_selesai' => now()->addYears(2)]);
    
    $k10 = Kelas::firstOrCreate(['tingkat' => 10, 'nama_kelas' => 'X - TEST'], ['jurusan' => 'RPL', 'is_aktif' => true]);
    $k11 = Kelas::firstOrCreate(['tingkat' => 11, 'nama_kelas' => 'XI - TEST'], ['jurusan' => 'RPL', 'is_aktif' => true]);
    $k12 = Kelas::firstOrCreate(['tingkat' => 12, 'nama_kelas' => 'XII - TEST'], ['jurusan' => 'RPL', 'is_aktif' => true]);
    
    $r10 = Rombel::firstOrCreate(['tahun_ajaran_id' => $ta1->id, 'kelas_id' => $k10->id, 'nama_rombel' => 'Rombel 10'], ['is_aktif' => true]);
    $r11 = Rombel::firstOrCreate(['tahun_ajaran_id' => $ta2->id, 'kelas_id' => $k11->id, 'nama_rombel' => 'Rombel 11'], ['is_aktif' => true]);

    $student = Student::create([
        'nis' => '54321', 
        'name' => 'Siswa Uji Coba', 
        'gender' => 'L', 
        'status' => 'active', 
        'kelas_id' => $k10->id
    ]);

    StudentRombel::create([
        'student_id' => $student->id, 
        'rombel_id' => $r10->id, 
        'tahun_ajaran_id' => $ta1->id
    ]);
    echo "1. Siswa Dibuat dan dimasukkan ke Rombel 10.\n";

    echo "\n=== SKENARIO 1: SISWA PINDAH KELUAR ===\n";
    $req = \Illuminate\Http\Request::create("/students/{$student->id}/status", 'POST', ['status' => 'pindah_keluar', 'catatan' => 'Pindah domisili']);
    $req->setLaravelSession(app('session')->driver());
    (new \App\Http\Controllers\StudentController())->ubahStatus($req, $student->id);
    
    $student->refresh();
    $srPindah = StudentRombel::where('student_id', $student->id)->where('rombel_id', $r10->id)->first();
    echo "Status: " . $student->status . " (Expected: pindah_keluar)\n";
    echo "Kelas ID: " . ($student->kelas_id ?? 'NULL') . " (Expected: NULL)\n";
    echo "Rombel Ditutup (Tanggal Keluar): " . ($srPindah->tanggal_keluar ?? 'NULL') . " (Expected: Not NULL)\n";

    echo "\n=== SKENARIO 2: SISWA MASUK KEMBALI DI TAHUN YANG SAMA ===\n";
    // Admin updates student data to assign to a class again
    $reqUpdate = \Illuminate\Http\Request::create("/students/{$student->id}", 'PUT', ['nis' => '54321', 'name' => 'Siswa Uji Coba', 'gender' => 'L', 'kelas_id' => $k10->id]);
    $reqUpdate->setLaravelSession(app('session')->driver());
    (new \App\Http\Controllers\StudentController())->update($reqUpdate, $student->id);
    
    // Admin changes status back to active
    $reqActive = \Illuminate\Http\Request::create("/students/{$student->id}/status", 'POST', ['status' => 'active', 'catatan' => 'Masuk lagi']);
    $reqActive->setLaravelSession(app('session')->driver());
    (new \App\Http\Controllers\StudentController())->ubahStatus($reqActive, $student->id);
    
    // Assign to rombel again
    $reqAssign = \Illuminate\Http\Request::create("/rombel/{$r10->id}/assign", 'POST', ['student_ids' => [$student->id]]);
    $reqAssign->setLaravelSession(app('session')->driver());
    (new \App\Http\Controllers\RombelController())->assignSiswa($reqAssign, $r10);
    
    $student->refresh();
    $srActive = StudentRombel::where('student_id', $student->id)->whereNull('tanggal_keluar')->first();
    echo "Status: " . $student->status . " (Expected: active)\n";
    echo "Kelas ID: " . $student->kelas_id . " (Expected: " . $k10->id . ")\n";
    echo "Assigned to Rombel ID: " . ($srActive->rombel_id ?? 'Failed') . " (Expected: " . $r10->id . ")\n";

    echo "\n=== SKENARIO 3: NAIK KELAS MASSAL (10 -> 11) ===\n";
    $reqNaik = \Illuminate\Http\Request::create("/naik-kelas/eksekusi", 'POST', ['catatan' => 'Naik tahun ajaran baru']);
    $reqNaik->setLaravelSession(app('session')->driver());
    (new \App\Http\Controllers\NaikKelasController())->eksekusi($reqNaik);
    
    $student->refresh();
    $sr10Closed = StudentRombel::where('student_id', $student->id)->where('rombel_id', $r10->id)->orderBy('id', 'desc')->first();
    echo "Status: " . $student->status . " (Expected: active)\n";
    echo "Kelas ID: " . $student->kelas_id . " (Expected: " . $k11->id . ")\n";
    echo "Rombel Lama Ditutup (Tanggal Keluar): " . ($sr10Closed->tanggal_keluar ?? 'NULL') . " (Expected: Not NULL)\n";
    
    // Masukkan ke rombel kelas 11
    StudentRombel::create([
        'student_id' => $student->id, 
        'rombel_id' => $r11->id, 
        'tahun_ajaran_id' => $ta2->id
    ]);

    echo "\n=== SKENARIO 4: NAIK KELAS MASSAL (11 -> 12) ===\n";
    (new \App\Http\Controllers\NaikKelasController())->eksekusi($reqNaik);
    $student->refresh();
    echo "Kelas ID: " . $student->kelas_id . " (Expected: " . $k12->id . ")\n";

    echo "\n=== SKENARIO 5: NAIK KELAS MASSAL DARI TINGKAT AKHIR (LULUS) ===\n";
    // We need to create a rombel for class 12 to make it 'active' for the loop
    $r12 = Rombel::firstOrCreate(['tahun_ajaran_id' => $ta2->id, 'kelas_id' => $k12->id, 'nama_rombel' => 'Rombel 12'], ['is_aktif' => true]);
    StudentRombel::create([
        'student_id' => $student->id, 
        'rombel_id' => $r12->id, 
        'tahun_ajaran_id' => $ta2->id
    ]);

    (new \App\Http\Controllers\NaikKelasController())->eksekusi($reqNaik);
    $student->refresh();
    $sr12Closed = StudentRombel::where('student_id', $student->id)->where('rombel_id', $r12->id)->orderBy('id', 'desc')->first();
    echo "Status: " . $student->status . " (Expected: graduated)\n";
    echo "Kelas ID: " . ($student->kelas_id ?? 'NULL') . " (Expected: NULL)\n";
    echo "Rombel Terakhir Ditutup (Tanggal Keluar): " . ($sr12Closed->tanggal_keluar ?? 'NULL') . " (Expected: Not NULL)\n";

} catch (\Exception $e) { echo "ERROR: " . $e->getMessage() . "\n"; }
DB::rollBack();
