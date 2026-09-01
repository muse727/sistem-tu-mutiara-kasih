<?php if (isset($component)) { $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54 = $component; } ?>
<?php if (isset($attributes)) { $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54 = $attributes; } ?>
<?php $component = App\View\Components\AppLayout::resolve([] + (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag ? $attributes->all() : [])); ?>
<?php $component->withName('app-layout'); ?>
<?php if ($component->shouldRender()): ?>
<?php $__env->startComponent($component->resolveView(), $component->data()); ?>
<?php if (isset($attributes) && $attributes instanceof Illuminate\View\ComponentAttributeBag): ?>
<?php $attributes = $attributes->except(\App\View\Components\AppLayout::ignoredParameterNames()); ?>
<?php endif; ?>
<?php $component->withAttributes([]); ?>
     <?php $__env->slot('header', null, []); ?> 
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">📖 Panduan Penggunaan</h2>
     <?php $__env->endSlot(); ?>

    <div class="max-w-4xl mx-auto py-8 px-4 sm:px-6 lg:px-8">

        
        <div class="bg-gradient-to-br from-sky-500 to-blue-600 rounded-2xl p-8 mb-8 text-white shadow-lg">
            <div class="flex items-start gap-4">
                <div class="text-5xl">📖</div>
                <div>
                    <h1 class="text-2xl font-bold mb-2">Selamat Datang di Sistem TU</h1>
                    <p class="text-sky-100 leading-relaxed">
                        Panduan ini akan membantu Anda menggunakan aplikasi dengan benar, mulai dari awal sampai akhir.
                        Ikuti urutan di bawah ini agar semua berjalan lancar.
                    </p>
                </div>
            </div>
        </div>

        
        <div class="bg-amber-50 border border-amber-200 rounded-2xl p-6 mb-6">
            <h2 class="text-lg font-bold text-amber-800 mb-4 flex items-center gap-2">
                ⚠️ Pertama Kali Pakai? Mulai dari Sini!
            </h2>
            <p class="text-amber-700 text-sm mb-4">Ikuti urutan ini sebelum mulai input data siswa atau tagihan:</p>
            <ol class="space-y-3">
                <li class="flex items-start gap-3">
                    <span class="w-7 h-7 bg-amber-500 text-white rounded-full flex items-center justify-center text-sm font-bold flex-shrink-0">1</span>
                    <div>
                        <p class="font-semibold text-amber-800">Isi Profil Sekolah</p>
                        <p class="text-amber-700 text-sm">Masuk ke menu <strong>Pengaturan → Profil Sekolah</strong>. Isi nama sekolah, alamat, dan nomor telepon. Ini akan muncul di kwitansi dan laporan.</p>
                    </div>
                </li>
                <li class="flex items-start gap-3">
                    <span class="w-7 h-7 bg-amber-500 text-white rounded-full flex items-center justify-center text-sm font-bold flex-shrink-0">2</span>
                    <div>
                        <p class="font-semibold text-amber-800">Buat Data Kelas</p>
                        <p class="text-amber-700 text-sm">Masuk ke menu <strong>Kelas</strong>. Tambahkan semua kelas yang ada di sekolah, contoh: Kelas 7A, 7B, 8A, dst.</p>
                    </div>
                </li>
                <li class="flex items-start gap-3">
                    <span class="w-7 h-7 bg-amber-500 text-white rounded-full flex items-center justify-center text-sm font-bold flex-shrink-0">3</span>
                    <div>
                        <p class="font-semibold text-amber-800">Buat Rombel & Tahun Ajaran</p>
                        <p class="text-amber-700 text-sm">Masuk ke menu <strong>Rombel</strong>. Aktifkan tahun ajaran yang sedang berjalan, lalu buat rombel (kelompok belajar) sesuai kelas.</p>
                    </div>
                </li>
                <li class="flex items-start gap-3">
                    <span class="w-7 h-7 bg-amber-500 text-white rounded-full flex items-center justify-center text-sm font-bold flex-shrink-0">4</span>
                    <div>
                        <p class="font-semibold text-amber-800">Input Data Siswa</p>
                        <p class="text-amber-700 text-sm">Baru setelah kelas & rombel ada, masuk ke menu <strong>Siswa</strong> dan tambahkan data siswa satu per satu atau lewat import Excel.</p>
                    </div>
                </li>
                <li class="flex items-start gap-3">
                    <span class="w-7 h-7 bg-amber-500 text-white rounded-full flex items-center justify-center text-sm font-bold flex-shrink-0">5</span>
                    <div>
                        <p class="font-semibold text-amber-800">Buat Tagihan</p>
                        <p class="text-amber-700 text-sm">Masuk ke menu <strong>Tagihan</strong>. Buat tagihan untuk siswa, pilih jenisnya (SPP, Uang Gedung, dll.), dan tentukan nominalnya.</p>
                    </div>
                </li>
            </ol>
        </div>

        
        <div class="space-y-4" x-data="{ open: null }">

            
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <button @click="open = open === 1 ? null : 1"
                        class="w-full flex items-center justify-between px-6 py-4 text-left hover:bg-gray-50 transition">
                    <div class="flex items-center gap-3">
                        <span class="text-2xl">🏠</span>
                        <div>
                            <p class="font-semibold text-gray-900">Dashboard</p>
                            <p class="text-sm text-gray-500">Ringkasan kondisi keuangan sekolah hari ini</p>
                        </div>
                    </div>
                    <svg class="w-5 h-5 text-gray-400 transition-transform" :class="open === 1 ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="open === 1" x-transition class="px-6 pb-5 border-t border-gray-50">
                    <div class="pt-4 space-y-3 text-sm text-gray-700">
                        <p>Dashboard adalah halaman pertama yang muncul setelah login. Di sini Anda bisa melihat ringkasan aktivitas hari ini secara langsung.</p>
                        <div class="grid grid-cols-1 gap-3 mt-3">
                            <div class="flex items-start gap-3 bg-sky-50 rounded-xl p-3">
                                <span class="text-xl">💰</span>
                                <div>
                                    <p class="font-semibold text-gray-800">Pemasukan Hari Ini</p>
                                    <p class="text-gray-600">Total uang yang masuk hari ini dari semua pembayaran siswa.</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-3 bg-red-50 rounded-xl p-3">
                                <span class="text-xl">👥</span>
                                <div>
                                    <p class="font-semibold text-gray-800">Siswa Belum Bayar</p>
                                    <p class="text-gray-600">Jumlah siswa yang masih punya tagihan belum dibayar bulan ini.</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-3 bg-green-50 rounded-xl p-3">
                                <span class="text-xl">🛒</span>
                                <div>
                                    <p class="font-semibold text-gray-800">Transaksi Kasir Hari Ini</p>
                                    <p class="text-gray-600">Jumlah transaksi penjualan dari kasir (kantin/koperasi) hari ini.</p>
                                </div>
                            </div>
                            <div class="flex items-start gap-3 bg-amber-50 rounded-xl p-3">
                                <span class="text-xl">📦</span>
                                <div>
                                    <p class="font-semibold text-gray-800">Catatan Stok</p>
                                    <p class="text-gray-600">Daftar barang yang stoknya menipis atau habis. Perlu segera diisi ulang.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <button @click="open = open === 2 ? null : 2"
                        class="w-full flex items-center justify-between px-6 py-4 text-left hover:bg-gray-50 transition">
                    <div class="flex items-center gap-3">
                        <span class="text-2xl">👩‍🎓</span>
                        <div>
                            <p class="font-semibold text-gray-900">Data Siswa</p>
                            <p class="text-sm text-gray-500">Kelola data siswa aktif, tambah, edit, dan naik kelas</p>
                        </div>
                    </div>
                    <svg class="w-5 h-5 text-gray-400 transition-transform" :class="open === 2 ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="open === 2" x-transition class="px-6 pb-5 border-t border-gray-50">
                    <div class="pt-4 space-y-4 text-sm text-gray-700">

                        <div>
                            <p class="font-bold text-gray-800 mb-2">➕ Cara Tambah Siswa Baru</p>
                            <ol class="list-decimal list-inside space-y-1 ml-2 text-gray-600">
                                <li>Klik tombol biru <strong>"+ Tambah Siswa"</strong> di kanan atas.</li>
                                <li>Isi nama lengkap siswa.</li>
                                <li>Isi NIS (Nomor Induk Siswa) — boleh dikosongkan kalau belum ada.</li>
                                <li>Pilih kelas siswa dari daftar yang tersedia.</li>
                                <li>Klik <strong>"Simpan"</strong>.</li>
                            </ol>
                        </div>

                        <div>
                            <p class="font-bold text-gray-800 mb-2">📥 Cara Import Banyak Siswa Sekaligus</p>
                            <ol class="list-decimal list-inside space-y-1 ml-2 text-gray-600">
                                <li>Klik tombol <strong>"Import CSV"</strong>.</li>
                                <li>Download dulu template yang tersedia agar formatnya benar.</li>
                                <li>Isi data siswa di file Excel/CSV tersebut.</li>
                                <li>Upload file yang sudah diisi, lalu klik Import.</li>
                            </ol>
                            <div class="mt-2 bg-blue-50 rounded-lg p-3 text-blue-700">
                                💡 <strong>Tips:</strong> Jika ada kolom yang tidak wajib, boleh dikosongkan. Yang wajib adalah: Nama dan Kelas.
                            </div>
                        </div>

                        <div>
                            <p class="font-bold text-gray-800 mb-2">🎓 Cara Naik Kelas</p>
                            <ol class="list-decimal list-inside space-y-1 ml-2 text-gray-600">
                                <li>Klik tombol kuning <strong>"Naik Kelas"</strong> di halaman daftar siswa.</li>
                                <li>Pilih kelas asal (misal: Kelas 7A).</li>
                                <li>Pilih kelas tujuan (misal: Kelas 8A).</li>
                                <li>Klik <strong>"Proses Naik Kelas"</strong>.</li>
                            </ol>
                            <div class="mt-2 bg-amber-50 rounded-lg p-3 text-amber-700">
                                ⚠️ <strong>Perhatian:</strong> Proses naik kelas tidak bisa dibatalkan. Pastikan pilihan kelas sudah benar sebelum dikonfirmasi.
                            </div>
                        </div>

                    </div>
                </div>
            </div>

            
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <button @click="open = open === 3 ? null : 3"
                        class="w-full flex items-center justify-between px-6 py-4 text-left hover:bg-gray-50 transition">
                    <div class="flex items-center gap-3">
                        <span class="text-2xl">🏫</span>
                        <div>
                            <p class="font-semibold text-gray-900">Kelas</p>
                            <p class="text-sm text-gray-500">Kelola daftar kelas yang ada di sekolah</p>
                        </div>
                    </div>
                    <svg class="w-5 h-5 text-gray-400 transition-transform" :class="open === 3 ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="open === 3" x-transition class="px-6 pb-5 border-t border-gray-50">
                    <div class="pt-4 space-y-3 text-sm text-gray-700">
                        <p>Menu ini digunakan untuk membuat dan mengatur daftar kelas di sekolah Anda.</p>
                        <div>
                            <p class="font-bold text-gray-800 mb-2">Cara Tambah Kelas Baru</p>
                            <ol class="list-decimal list-inside space-y-1 ml-2 text-gray-600">
                                <li>Klik <strong>"+ Tambah Kelas"</strong>.</li>
                                <li>Isi nama kelas, contoh: <em>7A</em>, <em>8B</em>, <em>IX-Unggulan</em>.</li>
                                <li>Isi tingkat kelas (angka), contoh: 7, 8, atau 9.</li>
                                <li>Klik <strong>"Simpan"</strong>.</li>
                            </ol>
                        </div>
                        <div class="bg-blue-50 rounded-lg p-3 text-blue-700">
                            💡 <strong>Tips:</strong> Buat semua kelas yang ada terlebih dahulu sebelum menambah data siswa. Kelas yang sudah punya siswa tidak bisa dihapus.
                        </div>
                    </div>
                </div>
            </div>

            
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <button @click="open = open === 4 ? null : 4"
                        class="w-full flex items-center justify-between px-6 py-4 text-left hover:bg-gray-50 transition">
                    <div class="flex items-center gap-3">
                        <span class="text-2xl">📋</span>
                        <div>
                            <p class="font-semibold text-gray-900">Rombel</p>
                            <p class="text-sm text-gray-500">Kelompok belajar dan tahun ajaran aktif</p>
                        </div>
                    </div>
                    <svg class="w-5 h-5 text-gray-400 transition-transform" :class="open === 4 ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="open === 4" x-transition class="px-6 pb-5 border-t border-gray-50">
                    <div class="pt-4 space-y-4 text-sm text-gray-700">
                        <p>Rombel adalah pengelompokan siswa dalam satu kelas untuk tahun ajaran tertentu. Fitur ini memudahkan pengelolaan tagihan per kelompok.</p>

                        <div>
                            <p class="font-bold text-gray-800 mb-2">📅 Cara Atur Tahun Ajaran</p>
                            <ol class="list-decimal list-inside space-y-1 ml-2 text-gray-600">
                                <li>Klik tombol <strong>"Kelola Tahun Ajaran"</strong> di pojok kanan atas halaman Rombel.</li>
                                <li>Tambah tahun ajaran baru, contoh: <em>2024/2025</em>.</li>
                                <li>Aktifkan tahun ajaran yang sedang berjalan dengan klik <strong>"Jadikan Aktif"</strong>.</li>
                            </ol>
                        </div>

                        <div>
                            <p class="font-bold text-gray-800 mb-2">➕ Cara Buat Rombel Baru</p>
                            <ol class="list-decimal list-inside space-y-1 ml-2 text-gray-600">
                                <li>Klik <strong>"+ Tambah Rombel"</strong>.</li>
                                <li>Pilih kelas yang sesuai.</li>
                                <li>Isi nama rombel jika perlu (opsional).</li>
                                <li>Wali kelas bersifat <em>opsional</em> — boleh diisi nanti.</li>
                                <li>Klik <strong>"Simpan"</strong>.</li>
                            </ol>
                        </div>

                        <div class="bg-amber-50 rounded-lg p-3 text-amber-700">
                            ⚠️ Pastikan tahun ajaran sudah aktif sebelum membuat rombel. Jika tahun ajaran belum aktif, rombel tidak bisa dibuat.
                        </div>
                    </div>
                </div>
            </div>

            
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <button @click="open = open === 5 ? null : 5"
                        class="w-full flex items-center justify-between px-6 py-4 text-left hover:bg-gray-50 transition">
                    <div class="flex items-center gap-3">
                        <span class="text-2xl">🧾</span>
                        <div>
                            <p class="font-semibold text-gray-900">Tagihan Sekolah</p>
                            <p class="text-sm text-gray-500">Buat, kelola, dan konfirmasi pembayaran tagihan siswa</p>
                        </div>
                    </div>
                    <svg class="w-5 h-5 text-gray-400 transition-transform" :class="open === 5 ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="open === 5" x-transition class="px-6 pb-5 border-t border-gray-50">
                    <div class="pt-4 space-y-4 text-sm text-gray-700">

                        <div>
                            <p class="font-bold text-gray-800 mb-2">➕ Cara Buat Tagihan Baru</p>
                            <ol class="list-decimal list-inside space-y-1 ml-2 text-gray-600">
                                <li>Klik tombol biru <strong>"Buat Tagihan"</strong> di kanan atas.</li>
                                <li>Pilih siswa yang akan ditagih (bisa satu atau banyak sekaligus).</li>
                                <li>Pilih jenis tagihan, contoh: SPP Januari, Uang Gedung, dll.</li>
                                <li>Isi nominal tagihan.</li>
                                <li>Klik <strong>"Simpan"</strong>.</li>
                            </ol>
                        </div>

                        <div>
                            <p class="font-bold text-gray-800 mb-2">✅ Cara Konfirmasi Pembayaran Tunai</p>
                            <ol class="list-decimal list-inside space-y-1 ml-2 text-gray-600">
                                <li>Cari tagihan siswa yang sudah bayar di daftar tagihan.</li>
                                <li>Klik tombol <strong>"Konfirmasi Bayar"</strong> atau ikon centang hijau.</li>
                                <li>Sistem akan otomatis mencatat waktu pembayaran dan nama petugas yang mengkonfirmasi.</li>
                                <li>Kwitansi bisa langsung dicetak setelah dikonfirmasi.</li>
                            </ol>
                        </div>

                        <div>
                            <p class="font-bold text-gray-800 mb-2">🖨️ Cara Cetak Kwitansi</p>
                            <ol class="list-decimal list-inside space-y-1 ml-2 text-gray-600">
                                <li>Buka tagihan yang sudah berstatus <strong>LUNAS</strong>.</li>
                                <li>Klik tombol <strong>"Cetak Kwitansi"</strong>.</li>
                                <li>Kwitansi akan terbuka di tab baru, siap untuk dicetak.</li>
                            </ol>
                        </div>

                        <div>
                            <p class="font-bold text-gray-800 mb-2">📊 Cara Export Data Tagihan</p>
                            <ol class="list-decimal list-inside space-y-1 ml-2 text-gray-600">
                                <li>Gunakan filter di atas tabel untuk menyaring data yang ingin diexport.</li>
                                <li>Klik tombol <strong>"Export CSV"</strong> (hijau) untuk file spreadsheet biasa.</li>
                                <li>Klik tombol <strong>"Export XLSX"</strong> (oranye) untuk file Excel lengkap dengan format rapi.</li>
                            </ol>
                        </div>

                        <div class="bg-green-50 rounded-lg p-3 text-green-700">
                            ✅ <strong>Status Tagihan:</strong><br>
                            • <strong>BELUM BAYAR</strong> = siswa belum membayar<br>
                            • <strong>LUNAS</strong> = sudah dibayar dan dikonfirmasi TU<br>
                        </div>
                    </div>
                </div>
            </div>

            
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <button @click="open = open === 6 ? null : 6"
                        class="w-full flex items-center justify-between px-6 py-4 text-left hover:bg-gray-50 transition">
                    <div class="flex items-center gap-3">
                        <span class="text-2xl">🛒</span>
                        <div>
                            <p class="font-semibold text-gray-900">Kasir & POS</p>
                            <p class="text-sm text-gray-500">Transaksi penjualan kantin, koperasi, atau toko sekolah</p>
                        </div>
                    </div>
                    <svg class="w-5 h-5 text-gray-400 transition-transform" :class="open === 6 ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="open === 6" x-transition class="px-6 pb-5 border-t border-gray-50">
                    <div class="pt-4 space-y-4 text-sm text-gray-700">

                        <div>
                            <p class="font-bold text-gray-800 mb-2">📦 Cara Tambah Barang Dagangan</p>
                            <ol class="list-decimal list-inside space-y-1 ml-2 text-gray-600">
                                <li>Buka menu <strong>Kasir → Daftar Barang</strong>.</li>
                                <li>Klik <strong>"+ Tambah Barang"</strong>.</li>
                                <li>Isi nama barang, kategori, harga jual, dan jumlah stok awal.</li>
                                <li>Upload foto barang (opsional).</li>
                                <li>Klik <strong>"Simpan"</strong>.</li>
                            </ol>
                        </div>

                        <div>
                            <p class="font-bold text-gray-800 mb-2">📦 Cara Buat Paket/Bundle</p>
                            <p class="text-gray-600 mb-2">Paket adalah kumpulan beberapa barang yang dijual sekaligus dengan harga khusus. Contoh: Paket Alat Tulis (pensil + penghapus + penggaris).</p>
                            <ol class="list-decimal list-inside space-y-1 ml-2 text-gray-600">
                                <li>Buka menu <strong>Kasir → Paket Barang</strong>.</li>
                                <li>Klik <strong>"+ Buat Paket"</strong>.</li>
                                <li>Beri nama paket dan tentukan harganya.</li>
                                <li>Tambahkan barang-barang yang termasuk dalam paket.</li>
                                <li>Klik <strong>"Simpan"</strong>.</li>
                            </ol>
                        </div>

                        <div>
                            <p class="font-bold text-gray-800 mb-2">💳 Cara Proses Transaksi</p>
                            <ol class="list-decimal list-inside space-y-1 ml-2 text-gray-600">
                                <li>Buka menu <strong>Kasir → Transaksi Baru</strong>.</li>
                                <li>Pilih siswa (opsional).</li>
                                <li>Pilih barang atau paket yang dibeli.</li>
                                <li>Atur jumlah barang.</li>
                                <li>Klik <strong>"Bayar"</strong> dan pilih metode pembayaran.</li>
                                <li>Struk transaksi bisa langsung dicetak.</li>
                            </ol>
                        </div>

                        <div>
                            <p class="font-bold text-gray-800 mb-2">📊 Cara Lihat Riwayat Transaksi</p>
                            <ol class="list-decimal list-inside space-y-1 ml-2 text-gray-600">
                                <li>Buka menu <strong>Kasir → Riwayat Transaksi</strong>.</li>
                                <li>Gunakan filter tanggal untuk menyaring data.</li>
                                <li>Klik transaksi untuk melihat detail atau mencetak ulang struk.</li>
                            </ol>
                        </div>

                        <div class="bg-blue-50 rounded-lg p-3 text-blue-700">
                            💡 Stok barang akan otomatis berkurang setiap ada transaksi. Pastikan stok selalu diperbarui agar laporan akurat.
                        </div>
                    </div>
                </div>
            </div>

            
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <button @click="open = open === 7 ? null : 7"
                        class="w-full flex items-center justify-between px-6 py-4 text-left hover:bg-gray-50 transition">
                    <div class="flex items-center gap-3">
                        <span class="text-2xl">⚙️</span>
                        <div>
                            <p class="font-semibold text-gray-900">Pengaturan Sekolah</p>
                            <p class="text-sm text-gray-500">Nama sekolah, logo, dan tampilan aplikasi</p>
                        </div>
                    </div>
                    <svg class="w-5 h-5 text-gray-400 transition-transform" :class="open === 7 ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="open === 7" x-transition class="px-6 pb-5 border-t border-gray-50">
                    <div class="pt-4 space-y-3 text-sm text-gray-700">
                        <p>Menu ini hanya bisa diakses oleh <strong>Admin</strong>. Digunakan untuk mengatur identitas sekolah yang tampil di kwitansi dan laporan.</p>
                        <div class="space-y-2">
                            <div class="flex items-start gap-2">
                                <span class="text-green-500 font-bold">✓</span>
                                <p><strong>Nama Sekolah</strong> — akan muncul di bagian atas kwitansi dan laporan export.</p>
                            </div>
                            <div class="flex items-start gap-2">
                                <span class="text-green-500 font-bold">✓</span>
                                <p><strong>Alamat & Telepon</strong> — informasi kontak sekolah di kwitansi.</p>
                            </div>
                            <div class="flex items-start gap-2">
                                <span class="text-green-500 font-bold">✓</span>
                                <p><strong>Logo Sekolah</strong> — logo yang diunggah akan muncul di header kwitansi.</p>
                            </div>
                            <div class="flex items-start gap-2">
                                <span class="text-green-500 font-bold">✓</span>
                                <p><strong>Warna Sidebar</strong> — sesuaikan warna tampilan aplikasi dengan identitas sekolah Anda.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            
            <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                <button @click="open = open === 8 ? null : 8"
                        class="w-full flex items-center justify-between px-6 py-4 text-left hover:bg-gray-50 transition">
                    <div class="flex items-center gap-3">
                        <span class="text-2xl">👤</span>
                        <div>
                            <p class="font-semibold text-gray-900">Profil & Akun</p>
                            <p class="text-sm text-gray-500">Ubah nama, email, dan password login</p>
                        </div>
                    </div>
                    <svg class="w-5 h-5 text-gray-400 transition-transform" :class="open === 8 ? 'rotate-180' : ''" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                    </svg>
                </button>
                <div x-show="open === 8" x-transition class="px-6 pb-5 border-t border-gray-50">
                    <div class="pt-4 space-y-3 text-sm text-gray-700">
                        <div>
                            <p class="font-bold text-gray-800 mb-2">Cara Ganti Password</p>
                            <ol class="list-decimal list-inside space-y-1 ml-2 text-gray-600">
                                <li>Klik menu <strong>"Profile User"</strong> di sidebar bawah.</li>
                                <li>Cari bagian <strong>"Ubah Password"</strong>.</li>
                                <li>Isi password lama, lalu password baru dua kali.</li>
                                <li>Klik <strong>"Simpan"</strong>.</li>
                            </ol>
                        </div>
                        <div class="bg-red-50 rounded-lg p-3 text-red-700">
                            🔒 <strong>Penting:</strong> Segera ganti password default setelah pertama kali login! Password default mudah ditebak dan tidak aman.
                        </div>
                    </div>
                </div>
            </div>

        </div>

        
        <div class="mt-8 bg-gray-50 rounded-2xl p-6 border border-gray-200">
            <h2 class="text-base font-bold text-gray-800 mb-4 flex items-center gap-2">
                💡 Tips Umum
            </h2>
            <ul class="space-y-2 text-sm text-gray-600">
                <li class="flex items-start gap-2">
                    <span class="text-sky-500 mt-0.5">•</span>
                    <span>Setelah melakukan perubahan penting, selalu klik tombol <strong>Simpan</strong> agar data tidak hilang.</span>
                </li>
                <li class="flex items-start gap-2">
                    <span class="text-sky-500 mt-0.5">•</span>
                    <span>Jika ada notifikasi berwarna <strong>merah</strong>, baca pesannya dengan teliti — biasanya ada data yang belum lengkap.</span>
                </li>
                <li class="flex items-start gap-2">
                    <span class="text-sky-500 mt-0.5">•</span>
                    <span>Notifikasi <strong>hijau</strong> berarti aksi berhasil disimpan.</span>
                </li>
                <li class="flex items-start gap-2">
                    <span class="text-sky-500 mt-0.5">•</span>
                    <span>Data yang sudah dihapus <strong>tidak bisa dikembalikan</strong>. Pastikan yakin sebelum menghapus.</span>
                </li>
                <li class="flex items-start gap-2">
                    <span class="text-sky-500 mt-0.5">•</span>
                    <span>Gunakan fitur <strong>Export</strong> secara rutin untuk backup data ke komputer Anda.</span>
                </li>
                <li class="flex items-start gap-2">
                    <span class="text-sky-500 mt-0.5">•</span>
                    <span>Logout setelah selesai menggunakan aplikasi, terutama jika memakai komputer bersama.</span>
                </li>
            </ul>
        </div>

        
        <div class="mt-6 bg-white rounded-2xl p-6 border border-gray-100 shadow-sm">
            <h2 class="text-base font-bold text-gray-800 mb-4 flex items-center gap-2">
                🔑 Siapa Bisa Apa?
            </h2>
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 rounded-lg">
                            <th class="text-left py-2 px-3 text-gray-600 font-semibold">Fitur</th>
                            <th class="text-center py-2 px-3 text-gray-600 font-semibold">Admin</th>
                            <th class="text-center py-2 px-3 text-gray-600 font-semibold">TU</th>
                            <th class="text-center py-2 px-3 text-gray-600 font-semibold">Staf</th>
                            <th class="text-center py-2 px-3 text-gray-600 font-semibold">Kepsek</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <tr>
                            <td class="py-2 px-3 text-gray-700">Lihat Dashboard</td>
                            <td class="text-center py-2 px-3 text-green-500 font-bold">✓</td>
                            <td class="text-center py-2 px-3 text-green-500 font-bold">✓</td>
                            <td class="text-center py-2 px-3 text-green-500 font-bold">✓</td>
                            <td class="text-center py-2 px-3 text-green-500 font-bold">✓</td>
                        </tr>
                        <tr class="bg-gray-50/50">
                            <td class="py-2 px-3 text-gray-700">Tambah/Edit Siswa</td>
                            <td class="text-center py-2 px-3 text-green-500 font-bold">✓</td>
                            <td class="text-center py-2 px-3 text-green-500 font-bold">✓</td>
                            <td class="text-center py-2 px-3 text-green-500 font-bold">✓</td>
                            <td class="text-center py-2 px-3 text-gray-300">—</td>
                        </tr>
                        <tr>
                            <td class="py-2 px-3 text-gray-700">Hapus Siswa</td>
                            <td class="text-center py-2 px-3 text-green-500 font-bold">✓</td>
                            <td class="text-center py-2 px-3 text-green-500 font-bold">✓</td>
                            <td class="text-center py-2 px-3 text-gray-300">—</td>
                            <td class="text-center py-2 px-3 text-gray-300">—</td>
                        </tr>
                        <tr class="bg-gray-50/50">
                            <td class="py-2 px-3 text-gray-700">Kelola Tagihan</td>
                            <td class="text-center py-2 px-3 text-green-500 font-bold">✓</td>
                            <td class="text-center py-2 px-3 text-green-500 font-bold">✓</td>
                            <td class="text-center py-2 px-3 text-green-500 font-bold">✓</td>
                            <td class="text-center py-2 px-3 text-green-500 font-bold">✓</td>
                        </tr>
                        <tr>
                            <td class="py-2 px-3 text-gray-700">Transaksi Kasir</td>
                            <td class="text-center py-2 px-3 text-green-500 font-bold">✓</td>
                            <td class="text-center py-2 px-3 text-green-500 font-bold">✓</td>
                            <td class="text-center py-2 px-3 text-green-500 font-bold">✓</td>
                            <td class="text-center py-2 px-3 text-gray-300">—</td>
                        </tr>
                        <tr class="bg-gray-50/50">
                            <td class="py-2 px-3 text-gray-700">Riwayat Transaksi</td>
                            <td class="text-center py-2 px-3 text-green-500 font-bold">✓</td>
                            <td class="text-center py-2 px-3 text-green-500 font-bold">✓</td>
                            <td class="text-center py-2 px-3 text-gray-300">—</td>
                            <td class="text-center py-2 px-3 text-green-500 font-bold">✓</td>
                        </tr>
                        <tr>
                            <td class="py-2 px-3 text-gray-700">Pengaturan Sekolah</td>
                            <td class="text-center py-2 px-3 text-green-500 font-bold">✓</td>
                            <td class="text-center py-2 px-3 text-gray-300">—</td>
                            <td class="text-center py-2 px-3 text-gray-300">—</td>
                            <td class="text-center py-2 px-3 text-gray-300">—</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>

        
        <div class="mt-8 text-center text-xs text-gray-400 pb-4">
            Panduan Sistem TU · Hubungi admin sekolah jika ada pertanyaan lebih lanjut.
        </div>

    </div>
 <?php echo $__env->renderComponent(); ?>
<?php endif; ?>
<?php if (isset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $attributes = $__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__attributesOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?>
<?php if (isset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54)): ?>
<?php $component = $__componentOriginal9ac128a9029c0e4701924bd2d73d7f54; ?>
<?php unset($__componentOriginal9ac128a9029c0e4701924bd2d73d7f54); ?>
<?php endif; ?>
<?php /**PATH /var/www/sistem-tu/resources/views/panduan/index.blade.php ENDPATH**/ ?>