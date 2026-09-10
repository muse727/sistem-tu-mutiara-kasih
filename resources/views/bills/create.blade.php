<x-app-layout>

    <div class="bg-slate-50 min-h-screen pb-12">
        <div class="bg-white border-b border-gray-200 px-6 py-8 shadow-sm"></div>
            
        <div class="bg-white shadow-xl sm:rounded-2xl p-8 border border-gray-100 relative overflow-hidden">
            <div class="absolute top-0 right-0 -mt-4 -mr-4 w-32 h-32 bg-blue-50 rounded-full blur-2xl opacity-50"></div>
            
            <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-10 relative z-10 gap-4">
                <div>
                    <h2 class="text-3xl font-extrabold text-gray-800 tracking-tight">📝 Generator Tagihan</h2>
                    <p class="text-sm text-gray-500 mt-1">Buat tagihan SPP Bulanan atau Tagihan Barang/Jasa dengan mudah.</p>
                </div>
                <a href="{{ route('bills.index') }}" class="group flex items-center gap-2 text-gray-500 hover:text-[#F56C3E] font-bold text-sm transition bg-gray-50 hover:bg-blue-50 px-4 py-2 rounded-lg border border-gray-200 hover:border-blue-200">
                    <svg class="w-4 h-4 group-hover:-translate-x-1 transition" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path></svg>
                    Kembali
                </a>
            </div>

            <form action="{{ route('bills.store') }}" method="POST" id="billForm">
                @csrf
                
                <div class="bg-blue-50/50 p-6 rounded-xl border border-gray-200 mb-8">
                    <div class="flex items-center gap-2 mb-4">
                        <div class="p-1.5 bg-blue-100 rounded-lg text-[#F56C3E]">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                        </div>
                        <h3 class="font-bold text-gray-800">Target Penerima Tagihan</h3>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                        <label class="cursor-pointer relative">
                            <input type="radio" name="target_type" value="student" class="peer sr-only" checked onclick="toggleTarget('student')">
                            <div class="p-4 bg-white border border-gray-200 rounded-xl hover:shadow-md transition peer-checked:border-[#F56C3E] peer-checked:ring-2 peer-checked:ring-blue-200 peer-checked:bg-white">
                                <span class="font-bold text-gray-700 flex items-center gap-2">
                                    <span class="w-4 h-4 rounded-full border border-gray-300 peer-checked:bg-[#F56C3E] peer-checked:border-[#F56C3E] block"></span>
                                    Per Siswa
                                </span>
                                <p class="text-xs text-gray-400 mt-1 pl-6">Kasus khusus (Denda/Susulan)</p>
                            </div>
                        </label>

                        <label class="cursor-pointer relative">
                            <input type="radio" name="target_type" value="class" class="peer sr-only" onclick="toggleTarget('class')">
                            <div class="p-4 bg-white border border-gray-200 rounded-xl hover:shadow-md transition peer-checked:border-[#F56C3E] peer-checked:ring-2 peer-checked:ring-blue-200 peer-checked:bg-white">
                                <span class="font-bold text-gray-700 flex items-center gap-2">
                                    <span class="w-4 h-4 rounded-full border border-gray-300 peer-checked:bg-[#F56C3E] peer-checked:border-[#F56C3E] block"></span>
                                    Satu Kelas
                                </span>
                                <p class="text-xs text-gray-400 mt-1 pl-6">Tagihan rutin (SPP/Gedung)</p>
                            </div>
                        </label>

                        <label class="cursor-pointer relative">
                            <input type="radio" name="target_type" value="all" class="peer sr-only" onclick="toggleTarget('all')">
                            <div class="p-4 bg-white border border-gray-200 rounded-xl hover:shadow-md transition peer-checked:border-[#F56C3E] peer-checked:ring-2 peer-checked:ring-blue-200 peer-checked:bg-white">
                                <span class="font-bold text-gray-700 flex items-center gap-2">
                                    <span class="w-4 h-4 rounded-full border border-gray-300 peer-checked:bg-[#F56C3E] peer-checked:border-[#F56C3E] block"></span>
                                    Semua Siswa
                                </span>
                                <p class="text-xs text-gray-400 mt-1 pl-6">Massal satu sekolah</p>
                            </div>
                        </label>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-1 gap-6">
                        <div id="input-student" class="w-full">
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Pilih Nama Siswa</label>
                            <select name="student_id" class="w-full rounded-lg border-gray-300 focus:ring-[#F56C3E] focus:border-[#F56C3E] shadow-sm">
                                <option value="">-- Cari Nama Siswa --</option>
                                @foreach($students as $student)
                                    <option value="{{ $student->id }}">{{ $student->name }} - {{ optional($student->kelas)->nama_kelas ?? '-' }}</option>
                                @endforeach
                            </select>
                        </div>

                        <div id="input-class" class="w-full hidden">
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Pilih Kelas</label>
                            <select name="kelas_id" class="w-full rounded-lg border-gray-300 focus:ring-[#F56C3E] focus:border-[#F56C3E] shadow-sm">
                                <option value="">-- Pilih Kelas --</option>
                                @foreach($kelasList as $kelas)
                                    <option value="{{ $kelas->id }}">{{ $kelas->nama_kelas }}</option>
                                @endforeach
                            </select>
                        </div>
                    </div>
                </div>

                <div class="mb-8">
                    <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Jenis Tagihan</label>
                    <select name="type" id="typeSelector" class="w-full rounded-lg border-gray-300 font-bold text-gray-800 shadow-sm focus:ring-[#F56C3E] focus:border-[#F56C3E] text-lg py-3" onchange="toggleFormType()">
                        <option value="SPP">📅 SPP Bulanan (Rutin)</option>
                        <option value="GEDUNG">🏢 Uang Gedung</option>
                        <option value="DAFTAR_ULANG">📚 Daftar Ulang</option>
                        <option value="KOMITE">🏫 Uang Komite / BP3</option>
                        <option value="KEGIATAN">🎯 Uang Kegiatan (OSIS/Ekskul)</option>
                        <option value="SERAGAM">👔 Uang Seragam</option>
                        <option value="BUKU">📖 Uang Buku / LKS</option>
                        <option value="UJIAN">✏️ Uang Ujian (UTS/UAS/UN)</option>
                        <option value="WISATA">🚌 Uang Wisata / Study Tour</option>
                        <option value="PERPISAHAN">🎓 Uang Perpisahan</option>
                        <option value="LAINNYA">⚡ Lainnya (isi deskripsi di judul tagihan)</option>
                    </select>
                    <p class="text-xs text-gray-500 mt-2 ml-1">*Pilih "SPP Bulanan" untuk mengaktifkan pengaturan tanggal & bulan.</p>
                </div>

                <div id="form-spp" class="bg-blue-50 p-8 rounded-xl border border-blue-200 mb-8 relative overflow-hidden">
                    <div class="absolute top-0 right-0 -mt-2 -mr-2 w-24 h-24 bg-blue-200 rounded-full opacity-20"></div>
                    
                    <div class="flex items-center gap-3 mb-6 relative z-10">
                        <div class="p-2 bg-[#F56C3E] text-white rounded-lg shadow-lg shadow-blue-200">
                            <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path></svg>
                        </div>
                        <div>
                            <h3 class="font-bold text-blue-900 text-lg">Pengaturan Periode SPP</h3>
                            <p class="text-xs text-blue-600">Tentukan bulan tagihan dan nominal harga.</p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6 relative z-10">
                        <div>
                            <label class="block text-xs font-bold text-blue-700 uppercase mb-2">Bulan Tagihan</label>
                            <select name="spp_month" class="w-full rounded-lg border-blue-300 focus:border-[#F56C3E] focus:ring-[#F56C3E] bg-white shadow-sm">
                                @foreach(range(1,12) as $m)
                                    <option value="{{ $m }}" {{ date('n') == $m ? 'selected' : '' }}>
                                        {{ date('F', mktime(0, 0, 0, $m, 1)) }}
                                    </option>
                                @endforeach
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-blue-700 uppercase mb-2">Tahun Tagihan</label>
                            <select name="spp_year" class="w-full rounded-lg border-blue-300 focus:border-[#F56C3E] focus:ring-[#F56C3E] bg-white shadow-sm">
                                <option value="{{ date('Y') }}">{{ date('Y') }}</option>
                                <option value="{{ date('Y')+1 }}">{{ date('Y')+1 }}</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-blue-700 uppercase mb-2">Nominal SPP (Rp)</label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <span class="text-gray-500 font-bold sm:text-sm">Rp</span>
                                </div>
                                <input type="number" name="spp_amount" placeholder="0" class="w-full rounded-lg border-blue-300 focus:border-[#F56C3E] focus:ring-[#F56C3E] pl-10 font-bold text-gray-800 shadow-sm text-lg">
                            </div>
                            <p class="text-[10px] text-[#171717] mt-1">*Anda bisa input harga berbeda untuk setiap kelas.</p>
                        </div>
                    </div>

                    <div class="flex items-start gap-3 bg-white/50 p-4 rounded-lg border border-gray-200">
                        <svg class="w-5 h-5 text-[#F56C3E] mt-0.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        <p class="text-sm text-blue-800">
                            <b>Sistem Otomatis:</b> Judul tagihan akan digenerate menjadi (contoh) <b>"SPP Agustus 2026"</b>. Tanggal jatuh tempo otomatis diset ke <b>tanggal 10</b> bulan tersebut sebagai informasi batas bayar.
                        </p>
                    </div>
                </div>

                <div id="form-regular" class="hidden">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Judul Tagihan <span class="text-[#F56C3E]">*</span></label>
                            <input type="text" name="name" placeholder="Contoh: Uang Gedung 2026/2027" class="w-full rounded-lg border-gray-300 focus:ring-[#F56C3E] focus:border-[#F56C3E] shadow-sm">
                            <p class="text-[10px] text-gray-400 mt-1">Judul ini akan muncul di kwitansi siswa.</p>
                        </div>
                        <div>
                            <label class="block text-xs font-bold text-gray-500 uppercase mb-2">Nominal (Rp) <span class="text-[#F56C3E]">*</span></label>
                            <div class="relative">
                                <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                    <span class="text-gray-500 font-bold sm:text-sm">Rp</span>
                                </div>
                                <input type="number" name="regular_amount" placeholder="0" min="1" class="w-full rounded-lg border-gray-300 focus:border-[#F56C3E] focus:ring-[#F56C3E] pl-10 font-bold text-gray-800 shadow-sm text-lg">
                            </div>
                            <p class="text-[10px] text-gray-400 mt-1">*Nominal sama untuk semua siswa yang dipilih.</p>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end pt-6 border-t border-gray-100">
                    <button type="submit" class="bg-gradient-to-r from-[#F56C3E] to-[#e45322] hover:from-[#e45322] hover:to-[#F56C3E] text-white font-bold py-3 px-8 rounded-xl shadow-lg shadow-blue-200 transition transform hover:-translate-y-1 flex items-center gap-2">
                        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path></svg>
                        Proses Tagihan
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
        // 1. Logic Toggle Target (Siswa vs Kelas vs Semua)
        function toggleTarget(type) {
            const inputStudent = document.getElementById('input-student');
            const inputClass = document.getElementById('input-class');

            if (type === 'student') {
                inputStudent.classList.remove('hidden');
                inputClass.classList.add('hidden');
            } else if (type === 'class') {
                inputStudent.classList.add('hidden');
                inputClass.classList.remove('hidden');
            } else { // All
                inputStudent.classList.add('hidden');
                inputClass.classList.add('hidden');
            }
        }

        // 2. Logic Toggle Tipe (SPP vs Regular)
        function toggleFormType() {
            const type = document.getElementById('typeSelector').value;
            const formSpp = document.getElementById('form-spp');
            const formRegular = document.getElementById('form-regular');
            
            // Ambil semua input di dalam form masing-masing
            const sppInputs = formSpp.querySelectorAll('input, select');
            const regularInputs = formRegular.querySelectorAll('input, select');

            if(type === 'SPP') {
                // Tampilkan Form SPP
                formSpp.classList.remove('hidden');
                formRegular.classList.add('hidden');
                
                // Aktifkan input SPP, Matikan input Regular
                // (Penting agar validasi controller tidak error)
                sppInputs.forEach(el => el.disabled = false);
                regularInputs.forEach(el => el.disabled = true);
            } else {
                // Tampilkan Form Regular
                formSpp.classList.add('hidden');
                formRegular.classList.remove('hidden');
                
                // Aktifkan input Regular, Matikan input SPP
                sppInputs.forEach(el => el.disabled = true);
                regularInputs.forEach(el => el.disabled = false);
            }
        }

        // 3. Logic Table Item (Manual vs Bundle)
        document.addEventListener('DOMContentLoaded', function() {
            
            // Jalankan toggle type saat load halaman
            toggleFormType();

            function updateRowLogic(row) {
                const typeSelector = row.querySelector('.type-selector');
                const inputName = row.querySelector('.input-name');
                const selectBundle = row.querySelector('.select-bundle');
                const inputPrice = row.querySelector('.input-price');
                const inputBundleId = row.querySelector('.input-bundle-id');

                // A. Handle Perubahan Tipe (Manual vs Paket)
                typeSelector.addEventListener('change', function() {
                    if (this.value === 'bundle') {
                        inputName.classList.add('hidden');
                        selectBundle.classList.remove('hidden');
                        inputPrice.setAttribute('readonly', true);
                        inputPrice.classList.add('bg-gray-100', 'cursor-not-allowed');
                    } else {
                        inputName.classList.remove('hidden');
                        selectBundle.classList.add('hidden');
                        inputPrice.removeAttribute('readonly');
                        inputPrice.classList.remove('bg-gray-100', 'cursor-not-allowed');
                        inputPrice.value = '';
                        inputName.value = '';
                        inputBundleId.value = '';
                    }
                    calculateTotal();
                });

                // B. Handle Pilihan Paket
                selectBundle.addEventListener('change', function() {
                    const selectedOption = this.options[this.selectedIndex];
                    const price = selectedOption.getAttribute('data-price');
                    const name = selectedOption.getAttribute('data-name');
                    
                    if (price) {
                        inputPrice.value = price;
                        inputName.value = name; 
                        inputBundleId.value = this.value; 
                        calculateTotal();
                    }
                });

                // C. Handle Perubahan Harga Manual
                inputPrice.addEventListener('input', calculateTotal);
                row.querySelector('input[name="item_qtys[]"]').addEventListener('input', calculateTotal);
                
                // D. Handle Hapus Baris dengan SWEETALERT2
                row.querySelector('.remove-row').addEventListener('click', function() {
                    const rowCount = document.querySelectorAll('.item-row').length;
                    
                    if (rowCount <= 1) {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Tidak Bisa Dihapus',
                            text: 'Minimal harus ada satu item dalam tagihan!',
                            confirmButtonColor: '#F56C3E'
                        });
                        return;
                    }

                    Swal.fire({
                        title: 'Hapus Item?',
                        text: "Item ini akan dihapus dari daftar tagihan.",
                        icon: 'warning',
                        showCancelButton: true,
                        confirmButtonColor: '#ef4444',
                        cancelButtonColor: '#777777',
                        confirmButtonText: 'Ya, Hapus!',
                        cancelButtonText: 'Batal'
                    }).then((result) => {
                        if (result.isConfirmed) {
                            row.remove(); 
                            calculateTotal(); 
                            
                            const Toast = Swal.mixin({
                                toast: true, position: 'top-end',
                                showConfirmButton: false, timer: 1500, timerProgressBar: true
                            });
                            Toast.fire({ icon: 'success', title: 'Item dihapus' });
                        }
                    });
                });
            }

            // Hitung Total Semua Baris
            function calculateTotal() {
                let total = 0;
                document.querySelectorAll('.item-row').forEach(row => {
                    const price = parseFloat(row.querySelector('input[name="item_prices[]"]').value) || 0;
                    const qty = parseFloat(row.querySelector('input[name="item_qtys[]"]').value) || 1;
                    total += (price * qty);
                });
                document.getElementById('grand-total').innerText = 'Rp ' + new Intl.NumberFormat('id-ID').format(total);
            }

            // Init Logic untuk baris pertama
            updateRowLogic(document.querySelector('.item-row'));

            // Tambah Baris Baru
            document.getElementById('add-row').addEventListener('click', function() {
                const firstRow = document.querySelector('.item-row');
                const newRow = firstRow.cloneNode(true);
                
                // Reset Value di baris baru
                newRow.querySelector('.input-name').value = '';
                newRow.querySelector('.input-price').value = '';
                newRow.querySelector('input[name="item_qtys[]"]').value = '1';
                newRow.querySelector('select.type-selector').value = 'manual';
                
                // Reset tampilan input manual vs bundle
                newRow.querySelector('.select-bundle').classList.add('hidden');
                newRow.querySelector('.input-name').classList.remove('hidden');
                newRow.querySelector('.input-price').removeAttribute('readonly');
                newRow.querySelector('.input-price').classList.remove('bg-gray-100', 'cursor-not-allowed');

                document.getElementById('items-container').appendChild(newRow);
                updateRowLogic(newRow); // Pasang event listener ke baris baru
            });
        });
    </script>
</x-app-layout>