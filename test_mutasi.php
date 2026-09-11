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

Auth::shouldReceive('id')->andReturn(1);
Auth::shouldReceive('user')->andReturn((object)['id' => 1, 'role' => 'admin']);
Auth::shouldReceive('check')->andReturn(true);
app('session')->driver()->start();

DB::beginTransaction();
try {
    echo "=== PREPARE MUTASI TEST ===\n";
    $ta1 = TahunAjaran::firstOrCreate(['nama' => '2025/2026'], ['is_aktif' => true, 'tanggal_mulai' => now(), 'tanggal_selesai' => now()->addYear()]);
    $k10A = Kelas::firstOrCreate(['tingkat' => 10, 'nama_kelas' => '10-A'], ['jurusan' => 'RPL', 'is_aktif' => true]);
    $k10B = Kelas::firstOrCreate(['tingkat' => 10, 'nama_kelas' => '10-B'], ['jurusan' => 'RPL', 'is_aktif' => true]);
    
    $r10A = Rombel::firstOrCreate(['tahun_ajaran_id' => $ta1->id, 'kelas_id' => $k10A->id, 'nama_rombel' => 'R10-A'], ['is_aktif' => true]);
    $r10B = Rombel::firstOrCreate(['tahun_ajaran_id' => $ta1->id, 'kelas_id' => $k10B->id, 'nama_rombel' => 'R10-B'], ['is_aktif' => true]);
    
    $siswa = Student::create(['nis' => '121212', 'name' => 'Siswa Mutasi', 'gender' => 'L', 'status' => 'active', 'kelas_id' => $k10A->id]);
    StudentRombel::create(['student_id' => $siswa->id, 'rombel_id' => $r10A->id, 'tahun_ajaran_id' => $ta1->id]);
    
    echo "Awal: Siswa di Rombel 10-A, Kelas ID: " . $siswa->kelas_id . "\n";
    
    echo "1. Hapus dari Rombel 10-A\n";
    $req = \Illuminate\Http\Request::create("/rombel/{$r10A->id}/students/{$siswa->id}/remove", 'DELETE');
    $req->setLaravelSession(app('session')->driver());
    (new \App\Http\Controllers\RombelController())->removeSiswa($req, $r10A, $siswa);
    
    $siswa->refresh();
    echo "Setelah Hapus: Kelas ID (Expect NULL): " . ($siswa->kelas_id ?? 'NULL') . "\n";
    
    echo "2. Masukkan ke Rombel 10-B\n";
    $reqAssign = \Illuminate\Http\Request::create("/rombel/{$r10B->id}/assign", 'POST', ['student_ids' => [$siswa->id]]);
    $reqAssign->setLaravelSession(app('session')->driver());
    (new \App\Http\Controllers\RombelController())->assignSiswa($reqAssign, $r10B);
    
    $siswa->refresh();
    echo "Setelah Assign: Kelas ID (Expect {$k10B->id}): " . $siswa->kelas_id . "\n";
    
    $srs = StudentRombel::where('student_id', $siswa->id)->get();
    echo "Total Riwayat Rombel: " . $srs->count() . "\n";
    foreach($srs as $sr) {
        echo "  - Rombel ID: {$sr->rombel_id}, Keluar: " . ($sr->tanggal_keluar ?? 'Masih Aktif') . "\n";
    }

} catch (\Exception $e) { echo "ERROR: " . $e->getMessage() . "\n"; }
DB::rollBack();
