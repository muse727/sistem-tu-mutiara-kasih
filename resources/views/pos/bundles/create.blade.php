<x-app-layout>

    <div class="bg-gradient-to-br from-slate-50 to-blue-50 min-h-screen py-12">
        <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="bg-gradient-to-br from-white to-slate-50 shadow-2xl sm:rounded-3xl p-8 border border-slate-200 relative overflow-hidden">
                <div class="absolute top-0 right-0 -mt-12 -mr-12 w-64 h-64 bg-gradient-to-br from-[#F56C3E]/5 to-transparent rounded-full blur-3xl opacity-50"></div>
                
                <div class="flex items-center gap-4 mb-8 relative z-10">
                    <div class="relative">
                        <div class="absolute inset-0 bg-gradient-to-r from-[#F56C3E] to-blue-400 rounded-2xl blur-lg opacity-30"></div>
                        <div class="relative bg-gradient-to-br from-[#F56C3E] to-blue-600 p-3 rounded-2xl shadow-lg">
                            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"></path>
                            </svg>
                        </div>
                    </div>
                    <div>
                        <h2 class="text-3xl font-bold text-slate-800 tracking-tight">Racik Paket Baru</h2>
                        <p class="text-sm text-slate-500 mt-1">Buat paket bundling untuk sistem POS dan penjualan</p>
                    </div>
                </div>

                <form action="{{ route('pos.bundles.store') }}" method="POST" id="bundleForm">
                    @csrf
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-8 mb-10">
                        <div class="space-y-6">
                            <div class="bg-gradient-to-br from-white to-slate-50 p-6 rounded-2xl border border-slate-200 shadow-sm">
                                <label class="block text-sm font-bold text-slate-600 mb-3 flex items-center gap-2">
                                    <svg class="w-5 h-5 text-[#F56C3E]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"></path>
                                    </svg>
                                    Detail Paket
                                </label>
                                
                                <div class="space-y-4">
                                    <div>
                                        <label class="block text-xs font-bold text-slate-500 uppercase tracking-wider mb-2">Nama Paket</label>
                                        <div class="relative">
                                            <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                <svg class="w-5 h-5 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"></path>
                                                </svg>
                                            </div>
                                            <input type="text" name="name" required placeholder="Contoh: Paket Seragam Lengkap Kelas X" 
                                                   class="w-full pl-10 rounded-xl border-slate-300 shadow-sm focus:ring-[#F56C3E] focus:border-[#F56C3E] text-slate-800 font-medium py-3.5 bg-white">
                                        </div>
                                    </div>
                                    

                                </div>
                            </div>
                            
                            <div class="bg-gradient-to-br from-white to-slate-50 p-6 rounded-2xl border border-slate-200 shadow-sm">
                                <h3 class="text-sm font-bold text-slate-600 mb-4 flex items-center gap-2">
                                    <svg class="w-5 h-5 text-[#F56C3E]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 7h6m0 10v-3m-3 3h.01M9 17h.01M9 11h.01M12 11h.01M15 11h.01M12 7h.01M15 7h.01"></path>
                                    </svg>
                                    Total Harga Paket
                                </h3>
                                <div class="bg-sky-50 border border-gray-200 rounded-xl px-4 py-4 text-center">
                                    <p class="text-xs font-semibold text-sky-600 uppercase tracking-wide mb-1">Total (otomatis dari komposisi)</p>
                                    <p class="text-2xl font-black text-sky-700" id="autoTotal">Rp 0</p>
                                    <p class="text-xs text-slate-400 mt-2">Dihitung dari harga jual satuan × qty</p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="space-y-6">
                            <div class="bg-gradient-to-br from-white to-slate-50 p-6 rounded-2xl border border-slate-200 shadow-sm">
                                <div class="flex items-center justify-between mb-4">
                                    <h3 class="text-sm font-bold text-slate-600 flex items-center gap-2">
                                        <svg class="w-5 h-5 text-[#F56C3E]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 11H5m14 0a2 2 0 012 2v6a2 2 0 01-2 2H5a2 2 0 01-2-2v-6a2 2 0 012-2m14 0V9a2 2 0 00-2-2M5 11V9a2 2 0 012-2m0 0V5a2 2 0 012-2h6a2 2 0 012 2v2M7 7h10"></path>
                                        </svg>
                                        Komposisi Paket
                                    </h3>
                                    <span class="text-xs font-bold text-slate-500 bg-slate-100 px-3 py-1 rounded-lg" id="itemCount">1 Item</span>
                                </div>
                                
                                <div id="items-container" class="space-y-4">
                                    <div class="row-item bg-white p-4 rounded-xl border border-slate-200 shadow-sm hover:shadow-md transition-shadow group">
                                        <div class="flex gap-4 items-center">
                                            <div class="relative">
                                                <div class="absolute inset-0 bg-gradient-to-br from-slate-200 to-slate-100 rounded-lg blur opacity-50"></div>
                                                <div class="relative w-10 h-10 rounded-lg bg-gradient-to-br from-slate-100 to-slate-50 border border-slate-200 flex items-center justify-center">
                                                    <span class="text-slate-500 font-bold text-sm">1</span>
                                                </div>
                                            </div>
                                            
                                            <div class="flex-1 space-y-3">
                                                <div>
                                                    <label class="block text-xs font-bold text-slate-500 mb-1">Pilih Barang</label>
                                                    <div class="relative">
                                                        <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none">
                                                            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"></path>
                                                            </svg>
                                                        </div>
                                                        <select name="products[]" required class="product-select w-full pl-10 rounded-lg border-slate-300 shadow-sm focus:ring-[#F56C3E] focus:border-[#F56C3E] text-sm py-2.5 bg-white">
                                                            <option value="">-- Pilih Barang --</option>
                                                            @foreach($products as $p)
                                                                <option value="{{ $p->id }}" data-price="{{ $p->price }}" data-stock="{{ $p->stock }}" data-name="{{ $p->name }}">{{ $p->name }} (Stok: {{ $p->stock }}, Harga: Rp {{ number_format($p->price, 0, ',', '.') }})
                                                                </option>
                                                            @endforeach
                                                        </select>
                                                    </div>
                                                </div>
                                                
                                                <div>
                                                    <label class="block text-xs font-bold text-slate-500 mb-1">Jumlah (Qty)</label>
                                                    <div class="flex items-center gap-2">
                                                        <div class="relative flex-1">
                                                            <input type="number" name="quantities[]" value="1" min="1" required 
                                                                   class="quantity-input w-full rounded-lg border-slate-300 shadow-sm focus:ring-[#F56C3E] focus:border-[#F56C3E] text-sm py-2.5 text-center bg-white">
                                                        </div>
                                                        <button type="button" class="remove-row p-2 text-slate-400 hover:text-rose-500 hover:bg-rose-50 rounded-lg transition-colors opacity-0 group-hover:opacity-100" disabled>
                                                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"></path>
                                                            </svg>
                                                        </button>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                
                                <button type="button" id="add-row" class="mt-6 w-full py-3.5 border-2 border-dashed border-slate-300 hover:border-[#F56C3E] text-slate-500 hover:text-[#F56C3E] rounded-xl font-bold text-sm transition-all duration-300 hover:bg-blue-50/50 flex items-center justify-center gap-2 group">
                                    <svg class="w-5 h-5 group-hover:scale-110 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"></path>
                                    </svg>
                                    Tambah Barang Lain
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="flex justify-end gap-4 pt-8 mt-8 border-t border-slate-200">
                        <a href="{{ route('pos.bundles.index') }}" class="px-6 py-3.5 bg-gradient-to-r from-slate-100 to-slate-200 hover:from-slate-200 hover:to-slate-300 text-slate-700 rounded-xl font-bold shadow-sm transition-all transform hover:-translate-y-0.5 flex items-center gap-2">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
                            </svg>
                            Batal
                        </a>
                        <button type="submit" class="px-8 py-3.5 bg-gradient-to-r from-[#F56C3E] to-blue-500 hover:from-blue-600 hover:to-[#F56C3E] text-white rounded-xl font-bold shadow-lg shadow-blue-200/50 transition-all transform hover:-translate-y-0.5 flex items-center gap-3">
                            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"></path>
                            </svg>
                            Simpan Paket
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            let rowCounter = 1;

            // Hitung total dari harga jual satuan × qty (bukan buy_price)
            function calculateCostAndProfit() {
                let total = 0;
                const rows = document.querySelectorAll('.row-item');

                rows.forEach(row => {
                    const select = row.querySelector('.product-select');
                    const quantityInput = row.querySelector('.quantity-input');
                    const selectedOption = select.options[select.selectedIndex];
                    
                    if (selectedOption && selectedOption.value) {
                        const price    = parseFloat(selectedOption.getAttribute('data-price')) || 0;
                        const quantity = parseInt(quantityInput.value) || 1;
                        total += price * quantity;
                    }
                });

                document.getElementById('itemCount').textContent = `${rows.length} Item${rows.length > 1 ? 's' : ''}`;
                document.getElementById('autoTotal').textContent = 'Rp ' + new Intl.NumberFormat('id-ID').format(total);
            }
            
            // Fungsi untuk membuat baris baru
            function createNewRow() {
                const container = document.getElementById('items-container');
                const firstRow = container.querySelector('.row-item');
                const newRow = firstRow.cloneNode(true);
                
                rowCounter++;
                newRow.querySelector('.relative span').textContent = rowCounter;
                newRow.querySelector('.product-select').value = '';
                newRow.querySelector('.quantity-input').value = '1';
                newRow.querySelector('.remove-row').disabled = false;
                newRow.querySelector('.remove-row').classList.remove('opacity-0');
                
                // Update event listeners for the new row
                updateRowEventListeners(newRow);
                
                container.appendChild(newRow);
                calculateCostAndProfit();
            }
            
            // Fungsi untuk update event listeners pada baris
            function updateRowEventListeners(row) {
                const select = row.querySelector('.product-select');
                const quantityInput = row.querySelector('.quantity-input');
                const removeBtn = row.querySelector('.remove-row');
                
                select.addEventListener('change', calculateCostAndProfit);
                quantityInput.addEventListener('input', calculateCostAndProfit);
                
                removeBtn.addEventListener('click', function() {
                    const rows = document.querySelectorAll('.row-item');
                    if (rows.length > 1) {
                        Swal.fire({
                            title: 'Hapus Barang?',
                            text: "Barang ini akan dihapus dari komposisi paket.",
                            icon: 'question',
                            showCancelButton: true,
                            confirmButtonColor: '#F56C3E',
                            cancelButtonColor: '#777777',
                            confirmButtonText: 'Ya, Hapus',
                            cancelButtonText: 'Batal'
                        }).then((result) => {
                            if (result.isConfirmed) {
                                row.remove();
                                updateRowNumbers();
                                calculateCostAndProfit();
                                
                                const Toast = Swal.mixin({
                                    toast: true,
                                    position: 'top-end',
                                    showConfirmButton: false,
                                    timer: 1500,
                                    timerProgressBar: true
                                });
                                Toast.fire({ icon: 'success', title: 'Barang dihapus' });
                            }
                        });
                    } else {
                        Swal.fire({
                            icon: 'warning',
                            title: 'Tidak Bisa Dihapus',
                            text: 'Minimal harus ada satu barang dalam paket!',
                            confirmButtonColor: '#F56C3E'
                        });
                    }
                });
            }
            
            // Fungsi untuk update nomor urut baris
            function updateRowNumbers() {
                const rows = document.querySelectorAll('.row-item');
                rows.forEach((row, index) => {
                    row.querySelector('.relative span').textContent = index + 1;
                });
                rowCounter = rows.length;
            }
            
            // Event listener untuk tombol tambah baris
            document.getElementById('add-row').addEventListener('click', createNewRow);
            
            // Setup event listeners untuk baris pertama
            document.querySelectorAll('.row-item').forEach(row => {
                updateRowEventListeners(row);
            });
            
            // Setup form validation
            document.getElementById('bundleForm').addEventListener('submit', function(e) {
                const rows = document.querySelectorAll('.row-item');
                let hasEmptyProduct = false;
                
                rows.forEach(row => {
                    const select = row.querySelector('.product-select');
                    if (!select.value) {
                        hasEmptyProduct = true;
                        select.classList.add('border-rose-500');
                    } else {
                        select.classList.remove('border-rose-500');
                    }
                });
                
                if (hasEmptyProduct) {
                    e.preventDefault();
                    Swal.fire({
                        icon: 'error',
                        title: 'Data Belum Lengkap',
                        text: 'Semua barang harus dipilih!',
                        confirmButtonColor: '#F56C3E'
                    });
                }
            });
            
            // Initialize calculation
            calculateCostAndProfit();
        });
    </script>
</x-app-layout>