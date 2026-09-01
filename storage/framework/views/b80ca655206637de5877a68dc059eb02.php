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
        <h2 class="font-semibold text-xl text-gray-800 leading-tight">
            <?php echo e(__('Executive Dashboard')); ?>

        </h2>
     <?php $__env->endSlot(); ?>

    <script src="https://cdn.jsdelivr.net/npm/apexcharts/dist/apexcharts.min.js"></script>

    
    <div class="bg-gray-50/50 space-y-8">

        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">

            
            <div class="bg-gradient-to-br from-[#38bdf8] via-[#0ea5e9] to-[#0284c7] p-6 rounded-2xl text-white shadow-xl shadow-sky-200 relative overflow-hidden group">
                <div class="relative z-10">
                    <div class="flex items-center justify-between mb-4">
                        <div class="p-3 bg-white/10 rounded-xl backdrop-blur-sm">
                            <svg class="w-6 h-6 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                        </div>
                        <span class="text-xs font-medium bg-white/20 px-2 py-1 rounded text-sky-50">Total Income</span>
                    </div>
                    <h3 class="text-3xl font-extrabold tracking-tight">Rp <?php echo e(number_format($totalIncomeToday, 0, ',', '.')); ?></h3>
                    <p class="text-sm text-sky-200 mt-1 flex items-center gap-1">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"></path></svg>
                        Hari ini
                    </p>
                </div>
                <div class="absolute -bottom-4 -right-4 w-24 h-24 bg-white/10 rounded-full group-hover:scale-150 transition-transform duration-500"></div>
            </div>

            
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 hover:shadow-lg transition-shadow duration-300">
                <div class="flex items-center justify-between mb-4">
                    <div class="p-3 bg-emerald-50 rounded-xl">
                        <svg class="w-6 h-6 text-emerald-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                    </div>
                    <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Tunai (Cash)</span>
                </div>
                <h3 class="text-2xl font-bold text-gray-800">Rp <?php echo e(number_format($totalCashToday, 0, ',', '.')); ?></h3>
                <p class="text-xs text-gray-500 mt-2">Dari Laci Kasir (TU + POS)</p>
            </div>

            
            <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100 hover:shadow-lg transition-shadow duration-300">
                <div class="flex items-center justify-between mb-4">
                    <div class="p-3 bg-blue-50 rounded-xl">
                        <svg class="w-6 h-6 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"></path></svg>
                    </div>
                    <span class="text-xs font-bold text-gray-400 uppercase tracking-wider">Online (App)</span>
                </div>
                <h3 class="text-2xl font-bold text-gray-800">Rp <?php echo e(number_format($billMidtransToday, 0, ',', '.')); ?></h3>
                <p class="text-xs text-gray-500 mt-2">Masuk via Payment Gateway</p>
            </div>

            
            <div class="bg-gradient-to-br from-rose-500 to-rose-600 p-6 rounded-2xl text-white shadow-lg shadow-rose-200 relative overflow-hidden">
                <div class="relative z-10">
                    <div class="flex items-center justify-between mb-2">
                        <span class="font-bold text-rose-100 text-sm">Tagihan Macet</span>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($unpaidStudents > 0): ?>
                        <div class="bg-white/20 px-2 py-1 rounded text-xs font-bold animate-pulse">Action Needed</div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </div>
                    <h3 class="text-3xl font-bold"><?php echo e($unpaidStudents); ?> <span class="text-lg font-normal opacity-80">Siswa</span></h3>
                    <p class="text-sm text-rose-100 mt-1">Belum Lunas</p>
                </div>
                <div class="absolute -right-2 -bottom-4 opacity-20 transform rotate-12">
                    <svg class="w-24 h-24" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"></path></svg>
                </div>
            </div>

        </div>

        
        <div class="grid grid-cols-1 lg:grid-cols-3 gap-8">

            
            <div class="lg:col-span-2 space-y-8">

                
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h3 class="text-lg font-bold text-gray-800">Tren Pemasukan Mingguan</h3>
                            <p class="text-sm text-gray-400">7 Hari Terakhir</p>
                        </div>
                    </div>
                    <div id="weeklyChart" class="w-full h-80"></div>
                </div>

                
                <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
                    <div class="p-6 border-b border-gray-100 flex justify-between items-center bg-gray-50/50">
                        <h3 class="font-bold text-gray-800 flex items-center gap-2">
                            <span class="relative flex h-3 w-3">
                                <span class="animate-ping absolute inline-flex h-full w-full rounded-full bg-green-400 opacity-75"></span>
                                <span class="relative inline-flex rounded-full h-3 w-3 bg-green-500"></span>
                            </span>
                            Live Feed Transaksi
                        </h3>
                        
                        <a href="<?php echo e(route('pos.history.index')); ?>" class="text-xs text-blue-600 hover:text-blue-800 font-medium">Lihat Semua →</a>
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
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $recentActivities; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $log): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                                <tr class="hover:bg-blue-50/30 transition group">
                                    <td class="px-6 py-4 text-gray-500 whitespace-nowrap">
                                        
                                        <?php echo e($log['time']?->format('H:i') ?? '-'); ?>

                                    </td>
                                    <td class="px-6 py-4">
                                        <div class="font-medium text-gray-800"><?php echo e($log['desc']); ?></div>
                                        <div class="text-xs text-gray-400"><?php echo e($log['type']); ?> Transaction</div>
                                    </td>
                                    <td class="px-6 py-4">
                                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($log['method'] === 'Online (App)'): ?>
                                            <span class="inline-flex items-center gap-1 bg-blue-100 text-blue-700 px-2 py-1 rounded text-xs font-bold border border-blue-200">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"></path></svg>
                                                Online
                                            </span>
                                        <?php else: ?>
                                            <span class="inline-flex items-center gap-1 bg-emerald-100 text-emerald-700 px-2 py-1 rounded text-xs font-bold border border-emerald-200">
                                                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"></path></svg>
                                                Cash
                                            </span>
                                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                                    </td>
                                    <td class="px-6 py-4 font-bold text-gray-700 text-right group-hover:text-blue-600 transition">
                                        Rp <?php echo e(number_format($log['amount'], 0, ',', '.')); ?>

                                    </td>
                                </tr>
                                <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                                <tr>
                                    <td colspan="4" class="px-6 py-8 text-center text-gray-400">Belum ada aktivitas hari ini.</td>
                                </tr>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </tbody>
                        </table>
                    </div>
                </div>

            </div>

            
            <div class="space-y-6">

                
                <div class="bg-white p-6 rounded-2xl shadow-sm border border-gray-100">
                    <h3 class="font-bold text-gray-800 mb-1">Analisa Metode Bayar (Bulan Ini)</h3>
                    
                    <p class="text-xs text-gray-400 mb-4">Tagihan Sekolah + Kantin</p>
                    <div id="paymentMethodChart" class="w-full flex justify-center"></div>
                    <div class="mt-4 space-y-2">
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500">Tunai (Cash + POS):</span>
                            <span class="font-bold text-gray-800">Rp <?php echo e(number_format($monthlyCashTotal, 0, ',', '.')); ?></span>
                        </div>
                        <div class="flex justify-between text-sm">
                            <span class="text-gray-500">Online (Midtrans):</span>
                            <span class="font-bold text-gray-800">Rp <?php echo e(number_format($monthlyMidtrans, 0, ',', '.')); ?></span>
                        </div>
                    </div>
                </div>

                
                <div class="bg-gradient-to-br from-orange-50 to-white p-6 rounded-2xl shadow-sm border border-orange-100">
                    <h3 class="font-bold text-orange-800 mb-4 flex items-center gap-2">
                        <div class="bg-orange-100 p-1.5 rounded-lg">
                            <svg class="w-5 h-5 text-orange-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"></path></svg>
                        </div>
                        Stok Menipis!
                    </h3>
                    <div class="space-y-3">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $lowStockItems; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $item): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                            <div class="flex items-center gap-3 p-3 bg-white rounded-xl shadow-sm border border-gray-100 hover:border-orange-200 transition">
                                <div class="w-2 h-2 rounded-full <?php echo e($item->stock <= 0 ? 'bg-red-500' : 'bg-orange-500 animate-pulse'); ?>"></div>
                                <div class="flex-1 min-w-0">
                                    <p class="text-sm font-bold text-gray-700 truncate"><?php echo e($item->name); ?></p>
                                    <p class="text-xs <?php echo e($item->stock <= 0 ? 'text-red-500' : 'text-orange-500'); ?> font-medium">
                                        <?php echo e($item->stock <= 0 ? 'Habis!' : 'Sisa: ' . $item->stock . ' pcs'); ?>

                                    </p>
                                </div>
                                <a href="<?php echo e(route('pos.items.edit', $item->id)); ?>"
                                   class="text-xs bg-orange-50 text-orange-600 px-2 py-1 rounded hover:bg-orange-100 transition flex-shrink-0">
                                    Restock
                                </a>
                            </div>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                            <div class="text-center py-4">
                                <svg class="w-10 h-10 text-green-200 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"></path></svg>
                                <p class="text-sm text-gray-500">Stok Aman Terkendali!</p>
                            </div>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
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
                        <?php $__currentLoopData = $chartData; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $data): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <?php echo e($data['total']); ?>,
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    ]
                }],
                chart: {
                    type: 'area',
                    height: 320,
                    fontFamily: 'Inter, sans-serif',
                    toolbar: { show: false },
                    animations: { enabled: true, easing: 'easeinout', speed: 800 }
                },
                colors: ['#0ea5e9'],
                fill: {
                    type: 'gradient',
                    gradient: { shadeIntensity: 1, opacityFrom: 0.7, opacityTo: 0.2, stops: [0, 90, 100] }
                },
                dataLabels: { enabled: false },
                stroke: { curve: 'smooth', width: 3 },
                xaxis: {
                    categories: [
                        <?php $__currentLoopData = $chartData; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $data): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            "<?php echo e($data['day']); ?>",
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
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
                grid: { borderColor: '#f3f4f6', strokeDashArray: 4 }
            };
            var weeklyChart = new ApexCharts(document.querySelector("#weeklyChart"), weeklyOptions);
            weeklyChart.render();

            // 2. GRAFIK PIE — BUG FIX: pakai monthlyCashTotal (sudah termasuk POS)
            var totalCash     = <?php echo e($monthlyCashTotal); ?>;
            var totalMidtrans = <?php echo e($monthlyMidtrans); ?>;

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
                colors: ['#0ea5e9', '#3b82f6'],
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
                                        if (<?php echo e($monthlyCashTotal); ?> === 0 && <?php echo e($monthlyMidtrans); ?> === 0) total = 0;
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

    
    
    
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        
        <div class="lg:col-span-2 bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-lg font-bold text-gray-800">Pendapatan Bulanan <?php echo e(now()->year); ?></h3>
                    <p class="text-sm text-gray-500 mt-0.5">Total income tagihan + POS per bulan</p>
                </div>
                <span class="text-xs bg-sky-50 text-sky-600 font-semibold px-3 py-1 rounded-full"><?php echo e(now()->year); ?></span>
            </div>
            <div id="monthlyBarChart" class="w-full h-72"></div>
        </div>

        
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6 flex flex-col">
            <div class="mb-4">
                <h3 class="text-lg font-bold text-gray-800">Status Pembayaran</h3>
                <p class="text-sm text-gray-500 mt-0.5">Siswa lunas vs belum bulan ini</p>
            </div>
            <div id="paidDonutChart" class="w-full flex-1 flex items-center justify-center" style="min-height:200px"></div>
            <div class="grid grid-cols-2 gap-3 mt-4">
                <div class="bg-emerald-50 rounded-xl p-3 text-center">
                    <div class="text-2xl font-bold text-emerald-600"><?php echo e($paidCount); ?></div>
                    <div class="text-xs text-emerald-500 mt-0.5">Sudah Lunas</div>
                </div>
                <div class="bg-red-50 rounded-xl p-3 text-center">
                    <div class="text-2xl font-bold text-red-500"><?php echo e($unpaidCount); ?></div>
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
                        <?php $__currentLoopData = $chartMonthly; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $m): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <?php echo e($m['total']); ?>,
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    ]
                }],
                xaxis: {
                    categories: [
                        <?php $__currentLoopData = $chartMonthly; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $m): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            '<?php echo e($m['month']); ?>',
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?>
                    ],
                    labels: { style: { fontSize: '11px', colors: '#94a3b8' } },
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
                        style: { fontSize: '11px', colors: '#94a3b8' }
                    }
                },
                fill: {
                    type: 'gradient',
                    gradient: { shade: 'light', type: 'vertical', shadeIntensity: 0.3, gradientToColors: ['#38bdf8'], inverseColors: false, opacityFrom: 1, opacityTo: 0.7 }
                },
                colors: ['#0ea5e9'],
                plotOptions: { bar: { borderRadius: 6, columnWidth: '55%' } },
                dataLabels: { enabled: false },
                grid: { borderColor: '#f1f5f9', strokeDashArray: 4, xaxis: { lines: { show: false } } },
                tooltip: {
                    y: { formatter: function(v) { return 'Rp ' + new Intl.NumberFormat('id-ID').format(v); } }
                }
            };
            var monthlyBarChart = new ApexCharts(document.querySelector('#monthlyBarChart'), monthlyBarOptions);
            monthlyBarChart.render();

            // ── Donut Chart Lunas vs Belum ──
            var paidVal   = <?php echo e($paidCount); ?>;
            var unpaidVal = <?php echo e($unpaidCount); ?>;
            // Hindari chart kosong
            var donutSeries = (paidVal === 0 && unpaidVal === 0) ? [1, 1] : [paidVal, unpaidVal];
            var paidDonutOptions = {
                chart: { type: 'donut', height: 200, fontFamily: 'Inter, sans-serif' },
                series: donutSeries,
                labels: ['Lunas', 'Belum Bayar'],
                colors: ['#10b981', '#f87171'],
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
                                        return '<?php echo e($paidPercent); ?>%';
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
<?php /**PATH /var/www/sistem-tu/resources/views/dashboard.blade.php ENDPATH**/ ?>