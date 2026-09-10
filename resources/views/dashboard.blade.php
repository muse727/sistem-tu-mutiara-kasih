<x-app-layout>
    <x-slot name="header">
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            {{ __('Executive Dashboard') }}
        </h2>
    </x-slot>

    <script src="https://cdn.jsdelivr.net/npm/apexcharts/dist/apexcharts.min.js"></script>

    {{-- BUG FIX: div wrapper sebelumnya langsung ditutup (</div>) sebelum konten --}}
    <div class="bg-gray-50/50 space-y-8">

        {{-- ── Kartu Summary 4 kolom ── --}}
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">

            {{-- Total Income Hari Ini --}}
            <div class="bg-[#FFFFFF] p-6 rounded-2xl shadow-sm border border-[#EDEDED] relative overflow-hidden group hover:shadow-lg transition-shadow duration-300">
                <div class="relative z-10">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-3 bg-[#F56C3E]/10 rounded-xl">
                            <svg class="w-6 h-6 text-[#F56C3E]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </div>
                        <span class="text-xs font-bold text-[#777777] uppercase tracking-wider">Total Income</span>
                    </div>
                    <h3 class="text-3xl font-extrabold tracking-tight text-[#171717]">Rp {{ number_format($totalIncomeToday, 0, ',', '.') }}</h3>
                    <p class="text-sm text-[#777777] mt-1 flex items-center gap-1">
                        <svg class="w-4 h-4 text-[#F56C3E]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                        Hari ini
                    </p>
                </div>
            </div>

            {{-- Tunai (Cash) --}}
            <div class="bg-[#FFFFFF] p-6 rounded-2xl shadow-sm border border-[#EDEDED] hover:shadow-lg transition-shadow duration-300">
                <div class="flex items-center justify-between mb-4">
                    <div class="p-3 bg-[#F56C3E]/10 rounded-xl">
                        <svg class="w-6 h-6 text-[#F56C3E]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    </div>
                    <span class="text-xs font-bold text-[#777777] uppercase tracking-wider">Tunai (Cash)</span>
                </div>
                <h3 class="text-2xl font-bold text-[#171717]">Rp {{ number_format($totalCashToday, 0, ',', '.') }}</h3>
                <p class="text-xs text-[#777777] mt-2">Dari Laci Kasir (TU + POS)</p>
            </div>

            {{-- Online / Midtrans --}}
            <div class="bg-[#FFFFFF] p-6 rounded-2xl shadow-sm border border-[#EDEDED] hover:shadow-lg transition-shadow duration-300">
                <div class="flex items-center justify-between mb-4">
                    <div class="p-3 bg-[#F56C3E]/10 rounded-xl">
                        <svg class="w-6 h-6 text-[#F56C3E]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                    </div>
                    <span class="text-xs font-bold text-[#777777] uppercase tracking-wider">Online (App)</span>
                </div>
                <h3 class="text-2xl font-bold text-[#171717]">Rp {{ number_format($billMidtransToday, 0, ',', '.') }}</h3>
                <p class="text-xs text-[#777777] mt-2">Masuk via Payment Gateway</p>
            </div>

            {{-- Tagihan Macet --}}
            <div class="bg-[#FFFFFF] p-6 rounded-2xl shadow-sm border border-[#EDEDED] hover:shadow-lg transition-shadow duration-300">
                <div class="flex items-center justify-between mb-4">
                    <div class="p-3 bg-red-50 rounded-xl">
                        <svg class="w-6 h-6 text-[#F56C3E]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                    </div>
                    <span class="text-xs font-bold text-[#777777] uppercase tracking-wider">Tagihan Macet</span>
                </div>
                <div class="flex items-end justify-between">
                    <div>
                        <h3 class="text-2xl font-bold text-[#171717]">{{ $unpaidStudents }} <span class="text-sm font-normal text-[#777777]">Siswa</span></h3>
                        <p class="text-xs text-[#777777] mt-2">Belum Lunas</p>
                    </div>
                    @if($unpaidStudents > 0)
                        <span class="bg-red-500 text-white px-2 py-1 rounded text-xs font-bold animate-pulse">Action Needed</span>
                    @endif
                </div>
            </div>

        </div>

        {{-- ── Konten Utama ── --}}
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            {{-- Kolom kiri: Chart + Live Feed --}}
            <div class="lg:col-span-2 space-y-8">

                {{-- Grafik Mingguan --}}
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h3 class="text-lg font-bold text-gray-800">Tren Pemasukan Mingguan</h3>
                            <p class="text-sm text-gray-400">7 Hari Terakhir</p>
                        </div>
                    </div>
                    <div id="weeklyChart" class="w-full h-80"></div>
                </div>

                {{-- Live Feed Transaksi --}}
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                        <h3 class="font-bold text-gray-800 flex items-center gap-2">
                            <span class="relative flex h-3 w-3">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
                            </span>
                            Live Feed Transaksi
                        </h3>
                        {{-- BUG FIX: link "Lihat Semua" sebelumnya href="#" --}}
                        <a href="{{ route('pos.history.index') }}" class="text-xs text-blue-600 hover:text-blue-800 font-medium">Lihat Semua →</a>
                    </div>

                    <div class="overflow-x-auto">
                        <table class="w-full text-sm text-left">
                            <thead class="bg-gray-50 text-gray-500 font-medium border-b border-gray-100">
                                <tr>
                                    <th class="px-6 py-3">Waktu</th>
                                    <th class="px-6 py-3">Keterangan</th>
                                    <th class="px-6 py-3">Metode</th>
                                    <th class="px-6 py-3 text-right">Nominal</th>
                                </tr>
                            </thead>
                            <tbody class="divide-y divide-gray-100">
                                @forelse($recentActivities as $log)
                                <tr class="hover:bg-blue-50/30 transition group">
                                    <td class="px-6 py-4 text-gray-500 whitespace-nowrap">
                                        {{-- BUG FIX: null-safe — $log['time'] sudah difilter di controller --}}
                                        {{ $log['time']?->format('H:i') ?? '-' }}
                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="font-medium text-gray-800">{{ $log['desc'] }}</div>
                                        <div class="text-xs text-gray-400">{{ $log['type'] }} Transaction</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        @if($log['method'] === 'Online (App)')
                                            <span class="inline-flex items-center gap-1 bg-[#F56C3E] text-white px-2 py-1 rounded text-xs font-bold shadow-sm">
                                                <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                                                Online
                                            </span>
                                        @else
                                            <span class="inline-flex items-center gap-1 bg-[#171717] text-white px-2 py-1 rounded text-xs font-bold shadow-sm">
                                                <svg class="w-3 h-3 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                                Cash
                                            </span>
                                        @endif
                                    </td>
                                    <td class="px-6 py-4 font-bold text-gray-700 text-right group-hover:text-blue-600 transition">
                                        Rp {{ number_format($log['amount'], 0, ',', '.') }}
                                    </td>
                                </tr>
                                @empty
                                <tr>
                                    <td colspan="4" class="px-6 py-8 text-center text-gray-400">Belum ada aktivitas hari ini.</td>
                                </tr>
                                @endforelse
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

            {{-- Kolom kanan: Pie Chart + Stok Menipis --}}
            <div class="space-y-6">

                {{-- Analisa Metode Bayar --}}
                <div class="bg-[#FFFFFF] p-6 rounded-2xl shadow-sm border border-[#EDEDED]">
                    <h3 class="font-bold text-[#171717] mb-1">Analisa Metode Bayar (Bulan Ini)</h3>
                    {{-- BUG FIX: label diperjelas — mencakup POS + SPP --}}
                    <p class="text-xs text-[#777777] mb-4">Tagihan Sekolah + Kantin</p>
                    <div id="paymentMethodChart" class="w-full flex justify-center"></div>
                    <div class="mt-4 space-y-2">
                        <div class="flex items-center text-sm">
                            <span class="w-3 h-3 rounded-full bg-[#171717] inline-block mr-2"></span>
                            <span class="text-[#777777] flex-1">Tunai (Cash + POS):</span>
                            <span class="font-bold text-[#171717]">Rp {{ number_format($monthlyCashTotal, 0, ',', '.') }}</span>
                        </div>
                        <div class="flex items-center text-sm">
                            <span class="w-3 h-3 rounded-full bg-[#F56C3E] inline-block mr-2"></span>
                            <span class="text-[#777777] flex-1">Online (Midtrans):</span>
                            <span class="font-bold text-[#171717]">Rp {{ number_format($monthlyMidtrans, 0, ',', '.') }}</span>
                        </div>
                    </div>
                </div>

                {{-- Stok Menipis --}}
                <div class="bg-[#FFFFFF] p-6 rounded-2xl shadow-sm border border-[#EDEDED]">
                    <h3 class="font-bold text-[#F56C3E] mb-4 flex items-center gap-2">
                        <div class="bg-[#F56C3E]/10 p-1.5 rounded-lg">
                            <svg class="w-5 h-5 text-[#F56C3E]" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                        </div>
                        Stok Menipis!
                    </h3>
                    <div class="space-y-3">
                        @forelse($lowStockItems as $item)
                            <div class="flex items-center gap-3 p-3 bg-white rounded-xl shadow-sm border border-gray-100 hover:border-orange-200 transition">
                                <div class="w-2 h-2 rounded-full {{ $item->stock <= 0 ? 'bg-red-500' : 'bg-orange-500 animate-pulse' }}"></div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-bold text-gray-700 truncate">{{ $item->name }}</p>
                                    <p class="text-xs {{ $item->stock <= 0 ? 'text-[#F56C3E]' : 'text-[#F56C3E]' }} font-medium">
                                        {{ $item->stock <= 0 ? 'Habis!' : 'Sisa: ' . $item->stock . ' pcs' }}
                                    </p>
                                </div>
                                <a href="{{ route('pos.items.edit', $item->id) }}"
                                   class="text-xs bg-white text-[#171717] border border-[#EDEDED] shadow-sm px-2 py-1 rounded hover:bg-orange-100 transition flex-shrink-0">
                                    Restock
                                </a>
                            </div>
                        @empty
                            <div class="text-center py-4">
                                <svg class="w-10 h-10 text-green-200 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                <p class="text-sm text-gray-500">Stok Aman Terkendali!</p>
                            </div>
                        @endforelse
                    </div>
                </div>

            </div>
        </div>

    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {

            // 1. GRAFIK MINGGUAN (AREA CHART)
            var weeklyOptions = {
                series: [{
                    name: 'Total Pemasukan',
                    data: [
                        @foreach($chartData as $data)
                            {{ $data['total'] }},
                        @endforeach
                    ]
                }],
                chart: {
                    type: 'area',
                    height: 320,
                    fontFamily: 'Inter, sans-serif',
                    toolbar: { show: false },
                    animations: { enabled: true, easing: 'easeinout', speed: 800 }
                },
                colors: ['#F56C3E'],
                fill: {
                    type: 'gradient',
                    gradient: { shadeIntensity: 1, opacityFrom: 0.7, opacityTo: 0.2, stops: [0, 90, 100] }
                },
                dataLabels: { enabled: false },
                stroke: { curve: 'smooth', width: 3 },
                xaxis: {
                    categories: [
                        @foreach($chartData as $data)
                            "{{ $data['day'] }}",
                        @endforeach
                    ],
                    axisBorder: { show: false },
                    axisTicks: { show: false }
                },
                yaxis: {
                    labels: {
                        formatter: function (value) {
                            return "Rp " + new Intl.NumberFormat('id-ID', { notation: 'compact' }).format(value);
                        }
                    }
                },
                tooltip: {
                    y: {
                        formatter: function (value) {
                            return "Rp " + new Intl.NumberFormat('id-ID').format(value);
                        }
                    }
                },
                grid: { borderColor: '#F5F5F5', strokeDashArray: 4 }
            };
            var weeklyChart = new ApexCharts(document.querySelector("#weeklyChart"), weeklyOptions);
            weeklyChart.render();

            // 2. GRAFIK PIE — BUG FIX: pakai monthlyCashTotal (sudah termasuk POS)
            var totalCash     = {{ $monthlyCashTotal }};
            var totalMidtrans = {{ $monthlyMidtrans }};

            // Kalau dua-duanya 0, dummy biar tidak error render
            if (totalCash === 0 && totalMidtrans === 0) {
                totalCash = 1;
            }

            var pieOptions = {
                series: [totalCash, totalMidtrans],
                labels: ['Tunai (Cash + POS)', 'Online (Midtrans)'],
                chart: {
                    type: 'donut',
                    height: 250,
                    fontFamily: 'Inter, sans-serif',
                },
                colors: ['#171717', '#F56C3E'],
                plotOptions: {
                    pie: {
                        donut: {
                            size: '65%',
                            labels: {
                                show: true,
                                total: {
                                    show: true,
                                    label: 'Total',
                                    formatter: function (w) {
                                        let total = w.globals.seriesTotals.reduce((a, b) => a + b, 0);
                                        // Kurangi dummy 1 kalau keduanya 0 aslinya
                                        if ({{ $monthlyCashTotal }} === 0 && {{ $monthlyMidtrans }} === 0) total = 0;
                                        return "Rp " + new Intl.NumberFormat('id-ID', { notation: 'compact' }).format(total);
                                    }
                                }
                            }
                        }
                    }
                },
                dataLabels: { enabled: false },
                legend: { position: 'bottom' },
                tooltip: {
                    y: {
                        formatter: function (value) {
                            return "Rp " + new Intl.NumberFormat('id-ID').format(value);
                        }
                    }
                }
            };
            var pieChart = new ApexCharts(document.querySelector("#paymentMethodChart"), pieOptions);
            pieChart.render();
        });
    </script>

    {{-- ═══════════════════════════════════════════════════════════ --}}
    {{-- FITUR 2: GRAFIK BULANAN 12 BULAN + PERSENTASE LUNAS/BELUM --}}
    {{-- ═══════════════════════════════════════════════════════════ --}}
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        {{-- Bar Chart 12 Bulan (2/3 width) --}}
        <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-lg font-bold text-gray-800">Pendapatan Bulanan {{ now()->year }}</h3>
                    <p class="text-sm text-gray-500 mt-0.5">Total income tagihan + POS per bulan</p>
                </div>
                <span class="text-xs bg-white text-[#171717] border border-[#EDEDED] shadow-sm font-semibold px-3 py-1 rounded-full">{{ now()->year }}</span>
            </div>
            <div id="monthlyBarChart" class="w-full h-72"></div>
        </div>

        {{-- Donut Chart Persentase Lunas (1/3 width) --}}
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 flex flex-col">
            <div class="mb-4">
                <h3 class="text-lg font-bold text-gray-800">Status Pembayaran</h3>
                <p class="text-sm text-gray-500 mt-0.5">Siswa lunas vs belum bulan ini</p>
            </div>
            <div id="paidDonutChart" class="w-full flex-1 flex items-center justify-center" style="min-height:200px"></div>
            <div class="grid grid-cols-2 gap-3 mt-4">
                <div class="bg-emerald-50 rounded-xl p-3 text-center">
                    <div class="text-2xl font-bold text-emerald-600">{{ $paidCount }}</div>
                    <div class="text-xs text-[#F56C3E] mt-0.5">Sudah Lunas</div>
                </div>
                <div class="bg-red-50 rounded-xl p-3 text-center">
                    <div class="text-2xl font-bold text-[#F56C3E]">{{ $unpaidCount }}</div>
                    <div class="text-xs text-red-400 mt-0.5">Belum Bayar</div>
                </div>
            </div>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function () {
            // ── Bar Chart 12 Bulan ──
            var monthlyBarOptions = {
                chart: { type: 'bar', height: 280, toolbar: { show: false }, fontFamily: 'Inter, sans-serif' },
                series: [{
                    name: 'Pendapatan',
                    data: [
                        @foreach($chartMonthly as $m)
                            {{ $m['total'] }},
                        @endforeach
                    ]
                }],
                xaxis: {
                    categories: [
                        @foreach($chartMonthly as $m)
                            '{{ $m['month'] }}',
                        @endforeach
                    ],
                    labels: { style: { fontSize: '11px', colors: '#777777' } },
                    axisBorder: { show: false },
                    axisTicks: { show: false }
                },
                yaxis: {
                    labels: {
                        formatter: function(v) {
                            if (v >= 1000000) return 'Rp ' + (v/1000000).toFixed(1) + 'jt';
                            if (v >= 1000) return 'Rp ' + (v/1000).toFixed(0) + 'rb';
                            return 'Rp ' + v;
                        },
                        style: { fontSize: '11px', colors: '#777777' }
                    }
                },
                fill: {
                    type: 'gradient',
                    gradient: { shade: 'light', type: 'vertical', shadeIntensity: 0.3, gradientToColors: ['#f9c4b3'], inverseColors: false, opacityFrom: 1, opacityTo: 0.7 }
                },
                colors: ['#F56C3E'],
                plotOptions: { bar: { borderRadius: 6, columnWidth: '55%' } },
                dataLabels: { enabled: false },
                grid: { borderColor: '#F5F5F5', strokeDashArray: 4, xaxis: { lines: { show: false } } },
                tooltip: {
                    y: { formatter: function(v) { return 'Rp ' + new Intl.NumberFormat('id-ID').format(v); } }
                }
            };
            var monthlyBarChart = new ApexCharts(document.querySelector('#monthlyBarChart'), monthlyBarOptions);
            monthlyBarChart.render();

            // ── Donut Chart Lunas vs Belum ──
            var paidVal   = {{ $paidCount }};
            var unpaidVal = {{ $unpaidCount }};
            // Hindari chart kosong
            var donutSeries = (paidVal === 0 && unpaidVal === 0) ? [1, 1] : [paidVal, unpaidVal];
            var paidDonutOptions = {
                chart: { type: 'donut', height: 200, fontFamily: 'Inter, sans-serif' },
                series: donutSeries,
                labels: ['Lunas', 'Belum Bayar'],
                colors: ['#F56C3E', '#171717'],
                plotOptions: {
                    pie: {
                        donut: {
                            size: '70%',
                            labels: {
                                show: true,
                                total: {
                                    show: true,
                                    label: 'Lunas',
                                    formatter: function() {
                                        return '{{ $paidPercent }}%';
                                    }
                                }
                            }
                        }
                    }
                },
                dataLabels: { enabled: false },
                legend: { show: false },
                tooltip: {
                    y: { formatter: function(v) { return v + ' siswa'; } }
                }
            };
            var paidDonutChart = new ApexCharts(document.querySelector('#paidDonutChart'), paidDonutOptions);
            paidDonutChart.render();
        });
    </script>
</x-app-layout>
