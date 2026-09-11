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
    $ta1 = TahunAjaran::firstOrCreate(['nama' => '2025/2026'], ['is_aktif' => true, 'tanggal_mulai' => now(), 'tanggal_selesai' => now()->addYear()]);
    $k10 = Kelas::firstOrCreate(['tingkat' => 10, 'nama_kelas' => '10-UJI'], ['jurusan' => 'IPA', 'is_aktif' => true]);
    $r10 = Rombel::firstOrCreate(['tahun_ajaran_id' => $ta1->id, 'kelas_id' => $k10->id, 'nama_rombel' => 'R10 UJI'], ['is_aktif' => true]);
    
    $siswa = Student::create(['nis' => '12345678', 'name' => 'Test Remove', 'gender' => 'L', 'status' => 'active', 'kelas_id' => $k10->id]);
    StudentRombel::create(['student_id' => $siswa->id, 'rombel_id' => $r10->id, 'tahun_ajaran_id' => $ta1->id]);
    
    echo "Before Remove: Status=" . $siswa->status . ", Kelas=" . $siswa->kelas_id . "\n";
    
    $req = \Illuminate\Http\Request::create("/rombel/{$r10->id}/students/{$siswa->id}/remove", 'DELETE');
    $req->setLaravelSession(app('session')->driver());
    (new \App\Http\Controllers\RombelController())->removeSiswa($req, $r10, $siswa);
    
    $siswa->refresh();
    echo "After Remove: Status=" . $siswa->status . ", Kelas=" . ($siswa->kelas_id ?? 'NULL') . "\n";
    
    $sr = StudentRombel::where('student_id', $siswa->id)->orderBy('id', 'desc')->first();
    echo "Rombel tanggal_keluar: " . ($sr->tanggal_keluar ?? 'NULL') . "\n";

} catch (\Exception $e) { echo "ERROR: " . $e->getMessage() . "\n"; }
DB::rollBack();
