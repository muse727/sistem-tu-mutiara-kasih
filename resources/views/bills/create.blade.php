<x-app-layout>

    <div class="bg-slate-50 min-h-screen pb-12 pt-6" x-data="billGenerator()">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            
            {{-- Card Utama --}}
            <div class="bg-white shadow-xl rounded-2xl p-6 sm:p-8 border border-gray-100 relative overflow-hidden">
                <div class="absolute top-0 right-0 -mt-4 -mr-4 w-32 h-32 bg-orange-50 rounded-full blur-2xl opacity-50"></div>
                
                {{-- Header --}}
                <div class="flex flex-col md:flex-row justify-between items-start md:items-center mb-8 relative z-10 gap-4">
                    <div>
                        <div class="flex items-center gap-3">
                            <div class="p-2.5 bg-orange-50 text-[#F56C3E] rounded-xl border border-orange-100">
                                <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"></path>
                                </svg>
                            </div>
                            <div>
                                <h2 class="text-2xl sm:text-3xl font-extrabold text-gray-900 tracking-tight">Generator Tagihan</h2>
                                <p class="text-sm text-gray-500 mt-0.5">Buat tagihan SPP Bulanan atau Tagihan Lainnya secara fleksibel.</p>
                            </div>
                        </div>
                    </div>
                    <a href="{{ route('bills.index') }}" class="group flex items-center gap-2 text-gray-600 hover:text-[#F56C3E] font-semibold text-sm transition bg-gray-50 hover:bg-orange-50 px-4 py-2.5 rounded-xl border border-gray-200 hover:border-orange-200 shadow-sm">
                        <svg class="w-4 h-4 group-hover:-translate-x-1 transition duration-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"></path>
                        </svg>
                        Kembali
                    </a>
                </div>

                <form action="{{ route('bills.store') }}" method="POST" id="billForm">
                    @csrf
                    
                    {{-- 1. TARGET PENERIMA TAGIHAN --}}
                    <div class="bg-gradient-to-br from-orange-50/40 via-white to-gray-50 p-6 rounded-2xl border border-orange-100/80 mb-8 shadow-sm">
                        <div class="flex items-center gap-2.5 mb-5">
                            <div class="p-1.5 bg-orange-100/80 rounded-lg text-[#F56C3E]">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0zm6 3a2 2 0 11-4 0 2 2 0 014 0zM7 10a2 2 0 11-4 0 2 2 0 014 0z"></path>
                                </svg>
                            </div>
                            <h3 class="font-bold text-gray-800 text-base">Target Penerima Tagihan</h3>
                        </div>

                        {{-- Radio Cards Target --}}
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4 mb-6">
                            {{-- Per Siswa --}}
                            <label class="cursor-pointer relative block" @click="targetType = 'student'">
                                <input type="radio" name="target_type" value="student" x-model="targetType" class="sr-only">
                                <div class="p-4 rounded-xl border-2 transition duration-200 bg-white shadow-sm flex flex-col justify-between h-full"
                                     :class="targetType === 'student' ? 'border-[#F56C3E] bg-orange-50/40 ring-2 ring-[#F56C3E]/20' : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50/50'">
                                    <div class="flex items-center gap-3">
                                        <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center transition-all"
                                             :class="targetType === 'student' ? 'border-[#F56C3E]' : 'border-gray-300'">
                                            <div class="w-2.5 h-2.5 rounded-full bg-[#F56C3E] transition-all" x-show="targetType === 'student'"></div>
                                        </div>
                                        <span class="font-bold text-gray-800 text-sm">Per Siswa</span>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-2 pl-8">Kasus khusus (Denda/Susulan/Individu)</p>
                                </div>
                            </label>

                            {{-- Satu Kelas --}}
                            <label class="cursor-pointer relative block" @click="targetType = 'class'">
                                <input type="radio" name="target_type" value="class" x-model="targetType" class="sr-only">
                                <div class="p-4 rounded-xl border-2 transition duration-200 bg-white shadow-sm flex flex-col justify-between h-full"
                                     :class="targetType === 'class' ? 'border-[#F56C3E] bg-orange-50/40 ring-2 ring-[#F56C3E]/20' : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50/50'">
                                    <div class="flex items-center gap-3">
                                        <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center transition-all"
                                             :class="targetType === 'class' ? 'border-[#F56C3E]' : 'border-gray-300'">
                                            <div class="w-2.5 h-2.5 rounded-full bg-[#F56C3E] transition-all" x-show="targetType === 'class'"></div>
                                        </div>
                                        <span class="font-bold text-gray-800 text-sm">Satu Kelas</span>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-2 pl-8">Tagihan per rombel/tingkat kelas</p>
                                </div>
                            </label>

                            {{-- Semua Siswa --}}
                            <label class="cursor-pointer relative block" @click="targetType = 'all'">
                                <input type="radio" name="target_type" value="all" x-model="targetType" class="sr-only">
                                <div class="p-4 rounded-xl border-2 transition duration-200 bg-white shadow-sm flex flex-col justify-between h-full"
                                     :class="targetType === 'all' ? 'border-[#F56C3E] bg-orange-50/40 ring-2 ring-[#F56C3E]/20' : 'border-gray-200 hover:border-gray-300 hover:bg-gray-50/50'">
                                    <div class="flex items-center gap-3">
                                        <div class="w-5 h-5 rounded-full border-2 flex items-center justify-center transition-all"
                                             :class="targetType === 'all' ? 'border-[#F56C3E]' : 'border-gray-300'">
                                            <div class="w-2.5 h-2.5 rounded-full bg-[#F56C3E] transition-all" x-show="targetType === 'all'"></div>
                                        </div>
                                        <span class="font-bold text-gray-800 text-sm">Semua Siswa</span>
                                    </div>
                                    <p class="text-xs text-gray-500 mt-2 pl-8">Massal seluruh siswa aktif</p>
                                </div>
                            </label>
                        </div>

                        {{-- Input Target Sesuai Pilihan --}}
                        <div class="pt-2">
                            {{-- Autocomplete Pencarian Siswa --}}
                            <div x-show="targetType === 'student'" x-transition class="w-full">
                                <label class="block text-xs font-bold text-gray-600 uppercase mb-2">Pilih Nama Siswa <span class="text-red-500">*</span></label>
                                
                                <input type="hidden" name="student_id" :value="selectedStudent ? selectedStudent.id : ''">

                                <div class="relative" @click.away="showStudentDropdown = false">
                                    <div class="relative">
                                        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-gray-400">
                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"></path>
                                            </svg>
                                        </div>
                                        <input type="text" 
                                               x-model="studentSearch" 
                                               @focus="showStudentDropdown = true" 
                                               placeholder="Ketik nama atau NIS siswa (contoh: Aditya, 1021)..."
                                               class="w-full pl-11 pr-10 py-3 bg-white border border-gray-300 rounded-xl focus:ring-2 focus:ring-[#F56C3E] focus:border-[#F56C3E] text-sm shadow-sm transition">
                                        <button type="button" 
                                                x-show="studentSearch.length > 0" 
                                                @click="clearStudent()" 
                                                class="absolute inset-y-0 right-0 pr-3 flex items-center text-gray-400 hover:text-gray-600">
                                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                            </svg>
                                        </button>
                                    </div>

                                    {{-- Dropdown Suggestion --}}
                                    <div x-show="showStudentDropdown && filteredStudents.length > 0" 
                                         x-transition
                                         class="absolute z-50 left-0 right-0 mt-1.5 bg-white border border-gray-200 rounded-xl shadow-2xl max-h-60 overflow-y-auto divide-y divide-gray-100">
                                        <template x-for="student in filteredStudents" :key="student.id">
                                            <div @click="selectStudent(student)" 
                                                 class="px-4 py-3 hover:bg-orange-50 cursor-pointer flex items-center justify-between transition">
                                                <div>
                                                    <p class="font-bold text-gray-800 text-sm" x-text="student.name"></p>
                                                    <p class="text-xs text-gray-500">NIS: <span x-text="student.nis || '-'"></span> • Kelas: <span class="font-medium text-[#F56C3E]" x-text="student.class_name"></span></p>
                                                </div>
                                                <span class="text-xs font-semibold text-gray-400 bg-gray-100 px-2 py-1 rounded-md">Pilih</span>
                                            </div>
                                        </template>
                                    </div>

                                    <div x-show="showStudentDropdown && filteredStudents.length === 0 && studentSearch.length > 0" 
                                         class="absolute z-50 left-0 right-0 mt-1.5 bg-white border border-gray-200 rounded-xl shadow-xl p-4 text-center text-xs text-gray-500">
                                        Tidak ada nama siswa yang cocok dengan "<span x-text="studentSearch" class="font-semibold"></span>"
                                    </div>
                                </div>

                                {{-- Selected Tag Badge --}}
                                <template x-if="selectedStudent">
                                    <div class="mt-3 inline-flex items-center gap-2 bg-orange-100 text-orange-900 px-3.5 py-1.5 rounded-lg text-xs font-semibold border border-orange-200">
                                        <svg class="w-4 h-4 text-[#F56C3E]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                                        </svg>
                                        <span>Terpilih: <strong x-text="selectedStudent.name"></strong> (<span x-text="selectedStudent.class_name"></span>)</span>
                                        <button type="button" @click="clearStudent()" class="ml-1 text-orange-700 hover:text-red-600">
                                            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                                            </svg>
                                        </button>
                                    </div>
                                </template>
                            </div>

                            {{-- Pilih Kelas --}}
                            <div x-show="targetType === 'class'" x-transition class="w-full">
                                <label class="block text-xs font-bold text-gray-600 uppercase mb-2">Pilih Kelas <span class="text-red-500">*</span></label>
                                <select name="kelas_id" class="w-full rounded-xl border-gray-300 focus:ring-2 focus:ring-[#F56C3E] focus:border-[#F56C3E] shadow-sm py-3 text-sm">
                                    <option value="">-- Pilih Kelas --</option>
                                    @foreach($kelasList as $kelas)
                                        <option value="{{ $kelas->id }}">{{ $kelas->nama_kelas }}</option>
                                    @endforeach
                                </select>
                            </div>
                        </div>
                    </div>

                    {{-- 2. JENIS TAGIHAN --}}
                    <div class="mb-8">
                        <label class="block text-xs font-bold text-gray-600 uppercase mb-2">Jenis Tagihan</label>
                        <div class="relative">
                            <select name="type" x-model="billType" class="w-full rounded-xl border-gray-300 font-bold text-gray-800 shadow-sm focus:ring-2 focus:ring-[#F56C3E] focus:border-[#F56C3E] text-base py-3.5 pl-4 pr-10">
                                <option value="SPP">SPP Bulanan (Rutin)</option>
                                <option value="GEDUNG">Uang Gedung / Pembangunan</option>
                                <option value="DAFTAR_ULANG">Daftar Ulang</option>
                                <option value="KOMITE">Uang Komite / BP3</option>
                                <option value="KEGIATAN">Uang Kegiatan (OSIS/Ekskul)</option>
                                <option value="SERAGAM">Uang Seragam</option>
                                <option value="BUKU">Uang Buku / LKS</option>
                                <option value="UJIAN">Uang Ujian (UTS/UAS/ANBK)</option>
                                <option value="WISATA">Uang Wisata / Study Tour</option>
                                <option value="PERPISAHAN">Uang Perpisahan</option>
                                <option value="LAINNYA">Lainnya (Bebas / Kustom)</option>
                            </select>
                        </div>
                    </div>

                    {{-- 3. PENGATURAN SPP BULANAN --}}
                    <div x-show="billType === 'SPP'" x-transition class="bg-gradient-to-br from-blue-50/60 via-white to-blue-50/30 p-6 sm:p-8 rounded-2xl border border-blue-100 mb-8 relative overflow-hidden shadow-sm">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="p-2.5 bg-[#F56C3E] text-white rounded-xl shadow-md shadow-orange-500/20">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-bold text-gray-900 text-lg">Pengaturan Periode & Jatuh Tempo SPP</h3>
                                <p class="text-xs text-gray-500">Tentukan periode bulan, nominal tagihan, dan batas waktu pembayaran.</p>
                            </div>
                        </div>
                        
                        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-5 mb-6">
                            {{-- Bulan --}}
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase mb-2">Bulan Tagihan</label>
                                <select name="spp_month" x-model="sppMonth" :disabled="billType !== 'SPP'" class="w-full rounded-xl border-gray-300 focus:border-[#F56C3E] focus:ring-2 focus:ring-[#F56C3E] bg-white shadow-sm py-2.5 text-sm">
                                    @foreach(range(1,12) as $m)
                                        <option value="{{ $m }}" {{ date('n') == $m ? 'selected' : '' }}>
                                            {{ date('F', mktime(0, 0, 0, $m, 1)) }}
                                        </option>
                                    @endforeach
                                </select>
                            </div>

                            {{-- Tahun --}}
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase mb-2">Tahun Tagihan</label>
                                <select name="spp_year" x-model="sppYear" :disabled="billType !== 'SPP'" class="w-full rounded-xl border-gray-300 focus:border-[#F56C3E] focus:ring-2 focus:ring-[#F56C3E] bg-white shadow-sm py-2.5 text-sm">
                                    <option value="{{ date('Y') }}">{{ date('Y') }}</option>
                                    <option value="{{ date('Y')+1 }}">{{ date('Y')+1 }}</option>
                                </select>
                            </div>

                            {{-- Tanggal Jatuh Tempo --}}
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase mb-2">
                                    Tanggal Jatuh Tempo
                                </label>
                                <div class="relative">
                                    <input type="date" 
                                           name="due_date" 
                                           x-model="sppDueDate"
                                           :disabled="billType !== 'SPP'"
                                           class="w-full rounded-xl border-gray-300 focus:border-[#F56C3E] focus:ring-2 focus:ring-[#F56C3E] bg-white shadow-sm py-2.5 text-sm">
                                </div>
                                <p class="text-[10px] text-gray-400 mt-1">Default: Tgl 10 bulan berjalan</p>
                            </div>

                            {{-- Nominal SPP --}}
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase mb-2">Nominal SPP (Rp)</label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                                        <span class="text-gray-500 font-bold text-xs">Rp</span>
                                    </div>
                                    <input type="number" 
                                           name="spp_amount" 
                                           placeholder="0" 
                                           min="1"
                                           :disabled="billType !== 'SPP'"
                                           class="w-full rounded-xl border-gray-300 focus:border-[#F56C3E] focus:ring-2 focus:ring-[#F56C3E] pl-10 font-bold text-gray-800 shadow-sm py-2.5 text-base">
                                </div>
                            </div>
                        </div>

                        <div class="flex items-center gap-3 bg-white p-4 rounded-xl border border-blue-100 shadow-xs">
                            <svg class="w-5 h-5 text-[#F56C3E] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path>
                            </svg>
                            <p class="text-xs text-gray-600 leading-relaxed">
                                <strong>Sistem Otomatis:</strong> Judul tagihan akan digenerate otomatis menjadi <span class="font-semibold text-gray-800" x-text="'SPP ' + getMonthName(sppMonth) + ' ' + sppYear"></span>. Jatuh tempo dapat diatur bebas sesuai kebijakan sekolah.
                            </p>
                        </div>
                    </div>

                    {{-- 4. PENGATURAN TAGIHAN NON-SPP --}}
                    <div x-show="billType !== 'SPP'" x-transition class="bg-gradient-to-br from-gray-50 via-white to-gray-50 p-6 sm:p-8 rounded-2xl border border-gray-200 mb-8 shadow-sm">
                        <div class="flex items-center gap-3 mb-6">
                            <div class="p-2.5 bg-gray-800 text-white rounded-xl shadow-md">
                                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"></path>
                                </svg>
                            </div>
                            <div>
                                <h3 class="font-bold text-gray-900 text-lg">Detail Tagihan Non-SPP</h3>
                                <p class="text-xs text-gray-500">Isi nama tagihan, nominal, dan batas waktu pembayaran.</p>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-3 gap-5">
                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase mb-2">Judul Tagihan <span class="text-red-500">*</span></label>
                                <input type="text" 
                                       name="name" 
                                       placeholder="Contoh: Uang Gedung 2026/2027" 
                                       :disabled="billType === 'SPP'"
                                       class="w-full rounded-xl border-gray-300 focus:ring-2 focus:ring-[#F56C3E] focus:border-[#F56C3E] shadow-sm py-2.5 text-sm">
                                <p class="text-[10px] text-gray-400 mt-1">Nama ini akan tercantum di kwitansi siswa.</p>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase mb-2">Tanggal Jatuh Tempo (Opsional)</label>
                                <input type="date" 
                                       name="due_date" 
                                       :disabled="billType === 'SPP'"
                                       class="w-full rounded-xl border-gray-300 focus:ring-2 focus:ring-[#F56C3E] focus:border-[#F56C3E] shadow-sm py-2.5 text-sm">
                                <p class="text-[10px] text-gray-400 mt-1">Kosongkan jika tidak ada batas waktu.</p>
                            </div>

                            <div>
                                <label class="block text-xs font-bold text-gray-700 uppercase mb-2">Nominal (Rp) <span class="text-red-500">*</span></label>
                                <div class="relative">
                                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none">
                                        <span class="text-gray-500 font-bold text-xs">Rp</span>
                                    </div>
                                    <input type="number" 
                                           name="regular_amount" 
                                           placeholder="0" 
                                           min="1" 
                                           :disabled="billType === 'SPP'"
                                           class="w-full rounded-xl border-gray-300 focus:border-[#F56C3E] focus:ring-2 focus:ring-[#F56C3E] pl-10 font-bold text-gray-800 shadow-sm py-2.5 text-base">
                                </div>
                            </div>
                        </div>
                    </div>

                    {{-- Submit Button --}}
                    <div class="flex justify-end pt-6 border-t border-gray-100">
                        <button type="submit" class="bg-gradient-to-r from-[#F56C3E] to-[#e45322] hover:from-[#e45322] hover:to-[#F56C3E] text-white font-bold py-3.5 px-8 rounded-xl shadow-lg shadow-orange-500/20 transition transform hover:-translate-y-0.5 flex items-center gap-2.5">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"></path>
                            </svg>
                            <span>Proses & Terbitkan Tagihan</span>
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        function billGenerator() {
            const rawStudents = {!! json_encode($studentsJson) !!};

            const monthNames = [
                '', 'Januari', 'Februari', 'Maret', 'April', 'Mei', 'Juni',
                'Juli', 'Agustus', 'September', 'Oktober', 'November', 'Desember'
            ];

            const currentYear = {{ date('Y') }};
            const currentMonth = {{ date('n') }};
            const pad = (n) => String(n).padStart(2, '0');

            return {
                targetType: 'student',
                billType: 'SPP',
                sppMonth: currentMonth,
                sppYear: currentYear,
                sppDueDate: `${currentYear}-${pad(currentMonth)}-10`,

                // Autocomplete Student State
                students: rawStudents,
                studentSearch: '',
                selectedStudent: null,
                showStudentDropdown: false,

                get filteredStudents() {
                    if (!this.studentSearch) return this.students.slice(0, 15);
                    const q = this.studentSearch.toLowerCase();
                    return this.students.filter(s => {
                        const nameMatch = s.name && s.name.toLowerCase().includes(q);
                        const nisMatch  = s.nis && s.nis.toLowerCase().includes(q);
                        const classMatch= s.class_name && s.class_name.toLowerCase().includes(q);
                        return nameMatch || nisMatch || classMatch;
                    }).slice(0, 20);
                },

                selectStudent(student) {
                    this.selectedStudent = student;
                    this.studentSearch = `${student.name} - ${student.class_name}`;
                    this.showStudentDropdown = false;
                },

                clearStudent() {
                    this.selectedStudent = null;
                    this.studentSearch = '';
                },

                getMonthName(m) {
                    return monthNames[parseInt(m)] || '';
                },

                init() {
                    this.$watch('sppMonth', (val) => {
                        this.updateDefaultDueDate();
                    });
                    this.$watch('sppYear', (val) => {
                        this.updateDefaultDueDate();
                    });
                },

                updateDefaultDueDate() {
                    this.sppDueDate = `${this.sppYear}-${pad(this.sppMonth)}-10`;
                }
            }
        }
    </script>
</x-app-layout>
