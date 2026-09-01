<x-app-layout>
<div class="p-6">

    {{-- ── Header ──────────────────────────────────────────────────────────── --}}
    <div class="flex flex-col md:flex-row justify-between items-start md:items-center gap-4 mb-6">
        <div>
            <div class="flex items-center gap-3 mb-2">
                <div class="p-2 bg-gradient-to-br from-[#0284c7]/10 to-[#0ea5e9]/10 rounded-xl">
                    <svg class="w-7 h-7 text-[#0284c7]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                    </svg>
                </div>
                <h1 class="text-3xl font-bold text-gray-900">Tahun Ajaran</h1>
            </div>
            <p class="text-gray-600 ml-12">Master data periode akademik. &nbsp;
                <a href="{{ route('rombel.index') }}" class="text-[#0284c7] hover:underline font-medium">Lihat Rombel →</a>
            </p>
        </div>
        <button onclick="openModal('modalCreate')"
                class="flex items-center gap-2 bg-[#0284c7] text-white px-4 py-2.5 rounded-xl hover:bg-[#0369a1] transition-all duration-300 font-medium shadow-sm text-sm">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
            </svg>
            Tambah Tahun Ajaran
        </button>
    </div>

    {{-- ── Stat Cards ──────────────────────────────────────────────────────── --}}
    @php
        $totalTA    = $tahunAjaran->count();
        $aktifTA    = $tahunAjaran->where('is_aktif', true)->first();
        $nonaktifTA = $tahunAjaran->where('is_aktif', false)->count();
    @endphp
    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
        {{-- Total Tahun Ajaran --}}
        <div class="bg-gradient-to-br from-sky-50 to-sky-100 border border-sky-200 rounded-xl p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-sky-800 font-medium">Total Tahun Ajaran</p>
                    <p class="text-2xl font-bold text-sky-600 mt-1">{{ $totalTA }}</p>
                </div>
                <div class="p-3 bg-sky-500/10 rounded-lg">
                    <svg class="w-6 h-6 text-sky-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                    </svg>
                </div>
            </div>
        </div>

        {{-- Tahun Ajaran Aktif --}}
        <div class="bg-gradient-to-br from-emerald-50 to-emerald-100 border border-emerald-200 rounded-xl p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-emerald-800 font-medium">Tahun Ajaran Aktif</p>
                    <p class="text-lg font-bold text-emerald-600 mt-1">{{ $aktifTA?->nama ?? '—' }}</p>
                    @if($aktifTA?->tanggal_mulai)
                        <p class="text-xs text-emerald-700 mt-0.5">
                            {{ \Carbon\Carbon::parse($aktifTA->tanggal_mulai)->format('M Y') }}
                            – {{ $aktifTA->tanggal_selesai ? \Carbon\Carbon::parse($aktifTA->tanggal_selesai)->format('M Y') : '?' }}
                        </p>
                    @endif
                </div>
                <div class="p-3 bg-emerald-500/10 rounded-lg">
                    <svg class="w-6 h-6 text-emerald-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                    </svg>
                </div>
            </div>
        </div>

        {{-- Non-aktif --}}
        <div class="bg-gradient-to-br from-slate-50 to-slate-100 border border-slate-200 rounded-xl p-4">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm text-slate-700 font-medium">Tahun Non-aktif</p>
                    <p class="text-2xl font-bold text-slate-500 mt-1">{{ $nonaktifTA }}</p>
                </div>
                <div class="p-3 bg-slate-400/10 rounded-lg">
                    <svg class="w-6 h-6 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 8h14M5 8a2 2 0 110-4h14a2 2 0 110 4M5 8v10a2 2 0 002 2h10a2 2 0 002-2V8m-9 4h4"/>
                    </svg>
                </div>
            </div>
        </div>
    </div>

    {{-- Flash messages --}}
    @if(session('success'))
    <div class="mb-4 bg-emerald-50 border border-emerald-200 text-emerald-700 px-4 py-3 rounded-xl text-sm">
        {{ session('success') }}
    </div>
    @endif
    @if(session('error'))
    <div class="mb-4 bg-red-50 border border-red-200 text-red-700 px-4 py-3 rounded-xl text-sm">
        {{ session('error') }}
    </div>
    @endif

    {{-- Table --}}
    <div class="bg-white rounded-xl border border-slate-100 shadow-sm overflow-hidden">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 border-b border-slate-100">
                <tr>
                    <th class="text-left px-4 py-3 font-semibold text-slate-600">Nama</th>
                    <th class="text-left px-4 py-3 font-semibold text-slate-600">Tanggal Mulai</th>
                    <th class="text-left px-4 py-3 font-semibold text-slate-600">Tanggal Selesai</th>
                    <th class="text-center px-4 py-3 font-semibold text-slate-600">Status</th>
                    <th class="text-center px-4 py-3 font-semibold text-slate-600">Aksi</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-50">
                @forelse($tahunAjaran as $ta)
                <tr class="hover:bg-slate-50 transition-colors">
                    <td class="px-4 py-3 font-medium text-slate-800">{{ $ta->nama }}</td>
                    <td class="px-4 py-3 text-slate-600">
                        {{ $ta->tanggal_mulai ? \Carbon\Carbon::parse($ta->tanggal_mulai)->format('d M Y') : '-' }}
                    </td>
                    <td class="px-4 py-3 text-slate-600">
                        {{ $ta->tanggal_selesai ? \Carbon\Carbon::parse($ta->tanggal_selesai)->format('d M Y') : '-' }}
                    </td>
                    <td class="px-4 py-3 text-center">
                        @if($ta->is_aktif)
                            <span class="bg-emerald-100 text-emerald-700 text-xs font-medium px-2.5 py-1 rounded-full">Aktif</span>
                        @else
                            <span class="bg-slate-100 text-slate-500 text-xs font-medium px-2.5 py-1 rounded-full">Nonaktif</span>
                        @endif
                    </td>
                    <td class="px-4 py-3 text-center">
                        <div class="flex items-center justify-center gap-2">
                            <button onclick="fetchAndEditTA({{ $ta->id }})"
                                    class="bg-amber-50 hover:bg-amber-100 text-amber-700 px-3 py-1.5 rounded-xl text-xs font-medium transition-all duration-200 border border-amber-200">
                                Edit
                            </button>
                            <button onclick="confirmDeleteTA('{{ route('tahun-ajaran.destroy', $ta) }}', '{{ $ta->nama }}')"
                                    class="bg-red-50 hover:bg-red-100 text-red-600 px-3 py-1.5 rounded-xl text-xs font-medium transition-all duration-200 border border-red-200">
                                Hapus
                            </button>
                        </div>
                    </td>
                </tr>
                @empty
                <tr>
                    <td colspan="5" class="px-4 py-10 text-center text-slate-400">Belum ada data tahun ajaran.</td>
                </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

{{-- ===== MODAL CREATE ===== --}}
<div id="modalCreate" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between p-6 border-b border-gray-100">
            <h3 class="text-lg font-bold text-gray-900">Tambah Tahun Ajaran</h3>
            <button onclick="closeModal('modalCreate')" class="text-gray-400 hover:text-gray-600 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <form id="formCreate" class="p-6 space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Nama Tahun Ajaran <span class="text-red-500">*</span></label>
                <input type="text" name="nama" placeholder="2025/2026"
                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent transition">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Tanggal Mulai</label>
                <input type="date" name="tanggal_mulai"
                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent transition">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Tanggal Selesai</label>
                <input type="date" name="tanggal_selesai"
                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent transition">
            </div>
            <div class="flex items-center gap-2">
                <input type="checkbox" name="is_aktif" id="createIsAktif" value="1"
                       class="w-4 h-4 rounded border-slate-300 text-sky-600 focus:ring-sky-500">
                <label for="createIsAktif" class="text-sm text-slate-700">Jadikan tahun ajaran aktif</label>
            </div>
            <p class="text-xs text-slate-400 -mt-2 ml-6">Mencentang ini akan menonaktifkan tahun ajaran lain yang sedang aktif.</p>

            <div id="createErrors" class="hidden bg-red-50 border border-red-200 rounded-xl p-4">
                <ul id="createErrorList" class="list-disc list-inside text-sm text-red-600 space-y-1"></ul>
            </div>

            <div class="flex items-center justify-end gap-3 pt-2 border-t border-gray-100">
                <button type="button" onclick="closeModal('modalCreate')"
                        class="px-4 py-2 border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 text-sm font-medium transition-colors">
                    Batal
                </button>
                <button type="submit" id="btnCreateSubmit"
                        class="px-6 py-2 bg-sky-600 hover:bg-sky-700 text-white rounded-xl text-sm font-medium transition-colors">
                    Simpan
                </button>
            </div>
        </form>
    </div>
</div>

{{-- ===== MODAL EDIT ===== --}}
<div id="modalEdit" class="fixed inset-0 z-50 hidden items-center justify-center bg-black/50 backdrop-blur-sm">
    <div class="bg-white rounded-2xl shadow-2xl w-full max-w-md mx-4 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between p-6 border-b border-gray-100">
            <h3 class="text-lg font-bold text-gray-900">Edit Tahun Ajaran</h3>
            <button onclick="closeModal('modalEdit')" class="text-gray-400 hover:text-gray-600 transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                </svg>
            </button>
        </div>
        <form id="formEdit" class="p-6 space-y-4">
            @csrf
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Nama Tahun Ajaran <span class="text-red-500">*</span></label>
                <input type="text" name="nama" id="editNama" placeholder="2025/2026"
                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent transition">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Tanggal Mulai</label>
                <input type="date" name="tanggal_mulai" id="editTanggalMulai"
                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent transition">
            </div>
            <div>
                <label class="block text-sm font-medium text-slate-700 mb-1.5">Tanggal Selesai</label>
                <input type="date" name="tanggal_selesai" id="editTanggalSelesai"
                       class="w-full border border-slate-300 rounded-lg px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-sky-500 focus:border-transparent transition">
            </div>
            <div class="flex items-center gap-2">
                <input type="checkbox" name="is_aktif" id="editIsAktif" value="1"
                       class="w-4 h-4 rounded border-slate-300 text-sky-600 focus:ring-sky-500">
                <label for="editIsAktif" class="text-sm text-slate-700">Jadikan tahun ajaran aktif</label>
            </div>
            <p class="text-xs text-slate-400 -mt-2 ml-6">Mencentang ini akan menonaktifkan tahun ajaran lain yang sedang aktif.</p>

            <div id="editErrors" class="hidden bg-red-50 border border-red-200 rounded-xl p-4">
                <ul id="editErrorList" class="list-disc list-inside text-sm text-red-600 space-y-1"></ul>
            </div>

            <div class="flex items-center justify-end gap-3 pt-2 border-t border-gray-100">
                <button type="button" onclick="closeModal('modalEdit')"
                        class="px-4 py-2 border border-gray-300 text-gray-700 rounded-xl hover:bg-gray-50 text-sm font-medium transition-colors">
                    Batal
                </button>
                <button type="submit" id="btnEditSubmit"
                        class="px-6 py-2 bg-sky-600 hover:bg-sky-700 text-white rounded-xl text-sm font-medium transition-colors">
                    Update
                </button>
            </div>
        </form>
    </div>
</div>

<script>
const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

function openModal(id) {
    const m = document.getElementById(id);
    m.classList.remove('hidden');
    m.classList.add('flex');
    document.body.style.overflow = 'hidden';
}
function closeModal(id) {
    const m = document.getElementById(id);
    m.classList.add('hidden');
    m.classList.remove('flex');
    document.body.style.overflow = '';
}
document.querySelectorAll('[id^="modal"]').forEach(modal => {
    modal.addEventListener('click', function(e) {
        if (e.target === this) closeModal(this.id);
    });
});

// ── Create ──
document.getElementById('formCreate').addEventListener('submit', async function(e) {
    e.preventDefault();
    const btn = document.getElementById('btnCreateSubmit');
    btn.disabled = true; btn.textContent = 'Menyimpan...';
    document.getElementById('createErrors').classList.add('hidden');

    const formData = new FormData(this);
    if (!this.querySelector('[name="is_aktif"]').checked) formData.delete('is_aktif');

    try {
        const res = await fetch('{{ route("tahun-ajaran.store") }}', {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: formData
        });
        const data = await res.json();
        if (res.ok && data.success) {
            closeModal('modalCreate');
            this.reset();
            Swal.fire({ icon: 'success', title: 'Berhasil!', text: data.message, timer: 2000, showConfirmButton: false, toast: true, position: 'top-end' });
            setTimeout(() => location.reload(), 2000);
        } else if (res.status === 422) {
            const errs = Object.values(data.errors).flat();
            document.getElementById('createErrorList').innerHTML = errs.map(e => `<li>${e}</li>`).join('');
            document.getElementById('createErrors').classList.remove('hidden');
        } else {
            Swal.fire({ icon: 'error', title: 'Gagal!', text: data.message || 'Terjadi kesalahan' });
        }
    } catch(err) {
        Swal.fire({ icon: 'error', title: 'Error!', text: 'Koneksi bermasalah, coba lagi.' });
    } finally {
        btn.disabled = false; btn.textContent = 'Simpan';
    }
});

// ── Fetch & Edit ──
async function fetchAndEditTA(id) {
    try {
        const res = await fetch(`/tahun-ajaran/${id}/edit`, {
            headers: { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken }
        });
        const data = await res.json();
        document.getElementById('editNama').value           = data.nama || '';
        document.getElementById('editTanggalMulai').value   = data.tanggal_mulai || '';
        document.getElementById('editTanggalSelesai').value = data.tanggal_selesai || '';
        document.getElementById('editIsAktif').checked      = !!data.is_aktif;
        document.getElementById('formEdit').dataset.id      = id;
        document.getElementById('editErrors').classList.add('hidden');
        openModal('modalEdit');
    } catch(e) {
        Swal.fire({ icon: 'error', title: 'Error!', text: 'Gagal memuat data.' });
    }
}

// ── Edit Submit ──
document.getElementById('formEdit').addEventListener('submit', async function(e) {
    e.preventDefault();
    const id  = this.dataset.id;
    const btn = document.getElementById('btnEditSubmit');
    btn.disabled = true; btn.textContent = 'Menyimpan...';
    document.getElementById('editErrors').classList.add('hidden');

    const formData = new FormData(this);
    formData.append('_method', 'PUT');
    if (!this.querySelector('[name="is_aktif"]').checked) formData.delete('is_aktif');

    try {
        const res = await fetch(`/tahun-ajaran/${id}`, {
            method: 'POST',
            headers: { 'X-CSRF-TOKEN': csrfToken, 'Accept': 'application/json' },
            body: formData
        });
        const data = await res.json();
        if (res.ok && data.success) {
            closeModal('modalEdit');
            Swal.fire({ icon: 'success', title: 'Berhasil!', text: data.message, timer: 2000, showConfirmButton: false, toast: true, position: 'top-end' });
            setTimeout(() => location.reload(), 2000);
        } else if (res.status === 422) {
            const errs = Object.values(data.errors).flat();
            document.getElementById('editErrorList').innerHTML = errs.map(e => `<li>${e}</li>`).join('');
            document.getElementById('editErrors').classList.remove('hidden');
        } else {
            Swal.fire({ icon: 'error', title: 'Gagal!', text: data.message || 'Terjadi kesalahan' });
        }
    } catch(err) {
        Swal.fire({ icon: 'error', title: 'Error!', text: 'Koneksi bermasalah, coba lagi.' });
    } finally {
        btn.disabled = false; btn.textContent = 'Update';
    }
});

// ── Delete ──
function confirmDeleteTA(url, nama) {
    Swal.fire({
        title: 'Hapus Tahun Ajaran?',
        text: `"${nama}" akan dihapus permanen!`,
        icon: 'warning',
        showCancelButton: true,
        confirmButtonColor: '#dc2626',
        cancelButtonColor: '#6b7280',
        confirmButtonText: 'Ya, Hapus!',
        cancelButtonText: 'Batal'
    }).then(result => {
        if (result.isConfirmed) {
            fetch(url, {
                method: 'POST',
                headers: { 'X-CSRF-TOKEN': csrfToken, 'Content-Type': 'application/x-www-form-urlencoded', 'Accept': 'application/json' },
                body: '_method=DELETE'
            }).then(async res => {
                const data = await res.json();
                if (res.ok && data.success) {
                    Swal.fire({ icon: 'success', title: 'Dihapus!', text: data.message, timer: 2000, showConfirmButton: false, toast: true, position: 'top-end' });
                    setTimeout(() => location.reload(), 2000);
                } else {
                    Swal.fire({ icon: 'error', title: 'Gagal!', text: data.message || 'Gagal menghapus data' });
                }
            }).catch(() => {
                Swal.fire({ icon: 'error', title: 'Error!', text: 'Koneksi bermasalah.' });
            });
        }
    });
}
</script>
</x-app-layout>
