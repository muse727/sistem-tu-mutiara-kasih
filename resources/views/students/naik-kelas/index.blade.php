<x-app-layout>
<div class="mb-8">
    {{-- Header --}}
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
        <div>
            <nav class="flex items-center gap-2 text-sm text-gray-500 mb-2">
                <a href="{{ route('students.index') }}" class="hover:text-[#0284c7] transition-colors">Siswa</a>
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                </svg>
                <span class="text-gray-700 font-medium">Naik Kelas Massal</span>
            </nav>
            <div class="flex items-center gap-3">
                <div class="p-2 bg-gradient-to-br from-[#0284c7]/10 to-[#0ea5e9]/10 rounded-xl">
                    <svg class="w-7 h-7 text-[#0284c7]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16V4m0 0L3 8m4-4l4 4m6 0v12m0 0l4-4m-4 4l-4-4"/>
                    </svg>
                </div>
                <div>
                    <h1 class="text-3xl font-bold text-gray-900">Naik Kelas Massal</h1>
                    <p class="text-sm text-gray-500 mt-0.5">Proses kenaikan kelas otomatis berdasarkan tingkat — <span class="font-semibold text-[#0284c7]">{{ $jenjang }}</span></p>
                </div>
            </div>
        </div>
        <a href="{{ route('students.index') }}"
           class="flex items-center gap-2 bg-white border border-gray-200 text-gray-700 px-4 py-2.5 rounded-xl hover:border-[#0284c7]/50 hover:shadow-md transition-all duration-300 font-medium shadow-sm text-sm">
            <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
            </svg>
            Kembali ke Daftar Siswa
        </a>
    </div>

    {{-- Flash messages --}}
    @if(session('success'))
    <div class="mb-6 bg-emerald-50 border border-emerald-200 text-emerald-800 px-5 py-4 rounded-xl flex items-center gap-3">
        <svg class="w-5 h-5 text-emerald-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <span class="font-medium">{{ session('success') }}</span>
    </div>
    @endif

    @if(session('error'))
    <div class="mb-6 bg-red-50 border border-red-200 text-red-800 px-5 py-4 rounded-xl flex items-center gap-3">
        <svg class="w-5 h-5 text-red-500 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
        </svg>
        <span class="font-medium">{{ session('error') }}</span>
    </div>
    @endif

    {{-- Statistik --}}
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 flex items-center gap-4">
            <div class="p-3 bg-blue-50 rounded-xl">
                <svg class="w-6 h-6 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
            </div>
            <div>
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">Naik Kelas</p>
                <p class="text-2xl font-bold text-gray-800">{{ $totalSiswa }}</p>
                <p class="text-xs text-gray-500">siswa akan naik tingkat</p>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 flex items-center gap-4">
            <div class="p-3 bg-emerald-50 rounded-xl">
                <svg class="w-6 h-6 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4M7.835 4.697a3.42 3.42 0 001.946-.806 3.42 3.42 0 014.438 0 3.42 3.42 0 001.946.806 3.42 3.42 0 013.138 3.138 3.42 3.42 0 00.806 1.946 3.42 3.42 0 010 4.438 3.42 3.42 0 00-.806 1.946 3.42 3.42 0 01-3.138 3.138 3.42 3.42 0 00-1.946.806 3.42 3.42 0 01-4.438 0 3.42 3.42 0 00-1.946-.806 3.42 3.42 0 01-3.138-3.138 3.42 3.42 0 00-.806-1.946 3.42 3.42 0 010-4.438 3.42 3.42 0 00.806-1.946 3.42 3.42 0 013.138-3.138z"/>
                </svg>
            </div>
            <div>
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">Lulus</p>
                <p class="text-2xl font-bold text-gray-800">{{ $totalLulus }}</p>
                <p class="text-xs text-gray-500">siswa dinyatakan lulus</p>
            </div>
        </div>
        <div class="bg-white rounded-xl border border-gray-100 shadow-sm p-5 flex items-center gap-4">
            <div class="p-3 bg-purple-50 rounded-xl">
                <svg class="w-6 h-6 text-purple-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 11h.01M12 11h.01M15 11h.01M4 19h16a2 2 0 002-2V7a2 2 0 00-2-2H4a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                </svg>
            </div>
            <div>
                <p class="text-xs font-bold text-gray-400 uppercase tracking-wider">Total Diproses</p>
                <p class="text-2xl font-bold text-gray-800">{{ $totalSiswa + $totalLulus }}</p>
                <p class="text-xs text-gray-500">total siswa aktif</p>
            </div>
        </div>
    </div>

    @if($mappings->isEmpty())
    {{-- Empty state --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-12 text-center">
        <div class="w-16 h-16 bg-gray-100 rounded-full flex items-center justify-center mx-auto mb-4">
            <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 13V6a2 2 0 00-2-2H6a2 2 0 00-2 2v7m16 0v5a2 2 0 01-2 2H6a2 2 0 01-2-2v-5m16 0h-2.586a1 1 0 00-.707.293l-2.414 2.414a1 1 0 01-.707.293h-3.172a1 1 0 01-.707-.293l-2.414-2.414A1 1 0 006.586 13H4"/>
            </svg>
        </div>
        <h3 class="text-lg font-bold text-gray-700 mb-2">Tidak ada siswa aktif</h3>
        <p class="text-sm text-gray-400">Belum ada siswa aktif yang bisa diproses naik kelas.</p>
    </div>
    @else

    {{-- Tabel Preview Mapping --}}
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden mb-6">
        <div class="px-6 py-4 border-b border-gray-100 flex items-center gap-3">
            <svg class="w-5 h-5 text-[#0284c7]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
            </svg>
            <h2 class="font-bold text-gray-800">Preview Mapping Otomatis</h2>
            <span class="ml-auto text-xs text-gray-400">Sistem otomatis menentukan kelas tujuan berdasarkan tingkat + jurusan</span>
        </div>
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead class="bg-gray-50 border-b border-gray-100">
                    <tr>
                        <th class="text-left px-6 py-3 font-semibold text-gray-500 uppercase text-xs tracking-wider">Kelas Asal</th>
                        <th class="text-center px-6 py-3 font-semibold text-gray-500 uppercase text-xs tracking-wider">Aksi</th>
                        <th class="text-left px-6 py-3 font-semibold text-gray-500 uppercase text-xs tracking-wider">Kelas Tujuan</th>
                        <th class="text-center px-6 py-3 font-semibold text-gray-500 uppercase text-xs tracking-wider">Jumlah Siswa</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-50">
                    @foreach($mappings as $m)
                    <tr class="hover:bg-gray-50 transition-colors">
                        <td class="px-6 py-4">
                            <span class="font-semibold text-gray-800">{{ $m['kelas_asal']->nama_kelas }}</span>
                            <span class="ml-2 text-xs text-gray-400">Tingkat {{ $m['kelas_asal']->tingkat }}</span>
                        </td>
                        <td class="px-6 py-4 text-center">
                            @if($m['is_tingkat_akhir'])
                                <span class="inline-flex items-center gap-1 px-3 py-1 bg-emerald-100 text-emerald-700 text-xs font-bold rounded-full">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    LULUS
                                </span>
                            @else
                                <span class="inline-flex items-center gap-1 px-3 py-1 bg-blue-100 text-blue-700 text-xs font-bold rounded-full">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 10l7-7m0 0l7 7m-7-7v18"/>
                                    </svg>
                                    NAIK KELAS
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4">
                            @if($m['is_tingkat_akhir'])
                                <span class="text-emerald-600 font-medium">Status: Lulus (graduated)</span>
                            @elseif($m['kelas_tujuan'])
                                <span class="font-semibold text-gray-800">{{ $m['kelas_tujuan']->nama_kelas }}</span>
                                <span class="ml-2 text-xs text-gray-400">Tingkat {{ $m['kelas_tujuan']->tingkat }}</span>
                            @else
                                <span class="text-red-500 font-medium flex items-center gap-1">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                                    </svg>
                                    Kelas tujuan tidak ditemukan — siswa akan dilewati
                                </span>
                            @endif
                        </td>
                        <td class="px-6 py-4 text-center">
                            <span class="font-bold text-gray-800">{{ $m['jumlah_siswa_aktif'] }}</span>
                            <span class="text-gray-400 text-xs"> siswa</span>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    {{-- Form Eksekusi --}}
    <div class="bg-white rounded-2xl border border-amber-200 shadow-sm p-6">
        <div class="flex items-start gap-3 mb-5">
            <div class="p-2 bg-amber-50 rounded-xl flex-shrink-0">
                <svg class="w-5 h-5 text-amber-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
            </div>
            <div>
                <h3 class="font-bold text-gray-900">Konfirmasi Eksekusi</h3>
                <p class="text-sm text-gray-500 mt-0.5">Proses ini akan memindahkan <strong>{{ $totalSiswa + $totalLulus }}</strong> siswa sesuai mapping di atas. <span class="text-red-600 font-semibold">Aksi ini tidak dapat dibatalkan.</span></p>
            </div>
        </div>

        <form method="POST" action="{{ route('naik-kelas.eksekusi') }}" id="form-eksekusi">
            @csrf
            <div class="mb-5">
                <label class="block text-sm font-semibold text-gray-700 mb-2">Catatan (opsional)</label>
                <textarea
                    name="catatan"
                    rows="2"
                    placeholder="Contoh: Naik kelas Tahun Ajaran 2026/2027"
                    class="w-full rounded-xl border-gray-300 focus:border-[#0284c7] focus:ring-[#0284c7] text-sm resize-none"
                ></textarea>
            </div>
            <div class="flex justify-end">
                <button type="button" onclick="konfirmasiEksekusi()"
                    class="flex items-center gap-2 bg-gradient-to-r from-amber-500 to-orange-500 hover:from-amber-600 hover:to-orange-600 text-white font-bold py-3 px-8 rounded-xl shadow-lg transition transform hover:-translate-y-0.5">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                    </svg>
                    Eksekusi Naik Kelas
                </button>
            </div>
        </form>
    </div>
    @endif
</div>

<script>
function konfirmasiEksekusi() {
    const totalNaik  = {{ $totalSiswa }};
    const totalLulus = {{ $totalLulus }};

    Swal.fire({
        title: 'Konfirmasi Naik Kelas',
        html: `
            <div class="text-left space-y-2 text-sm">
                <p>Kamu akan memproses:</p>
                <ul class="list-disc list-inside space-y-1 mt-2">
                    <li><strong>${totalNaik}</strong> siswa naik ke kelas berikutnya</li>
                    <li><strong>${totalLulus}</strong> siswa dinyatakan <span class="text-emerald-600 font-bold">lulus</span></li>
                </ul>
                <p class="mt-3 text-red-600 font-semibold">⚠️ Aksi ini tidak dapat dibatalkan.</p>
            </div>
        `,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#f59e0b',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Ya, Eksekusi!',
        cancelButtonText: 'Batal',
        reverseButtons: true,
    }).then(result => {
        if (result.isConfirmed) {
            const btn = document.querySelector('button[onclick="konfirmasiEksekusi()"]');
            btn.disabled = true;
            btn.innerHTML = '<svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg> Memproses...';
            document.getElementById('form-eksekusi').submit();
        }
    });
}
</script>
</x-app-layout>
