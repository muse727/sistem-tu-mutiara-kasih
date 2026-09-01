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
     <?php $__env->slot('header', null, []); ?> Audit Trail <?php $__env->endSlot(); ?>

    <div class="space-y-6">

        
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <div class="flex items-center gap-4">
                <div class="p-3 bg-red-50 rounded-xl">
                    <svg class="w-6 h-6 text-red-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z"/>
                    </svg>
                </div>
                <div>
                    <h2 class="text-xl font-bold text-gray-800">Audit Trail</h2>
                    <p class="text-sm text-gray-500 mt-0.5">Log aktivitas sistem — hanya dapat diakses oleh Admin</p>
                </div>
                <div class="ml-auto text-sm text-gray-400">
                    Total: <span class="font-semibold text-gray-700"><?php echo e($logs->total()); ?></span> log
                </div>
            </div>
        </div>

        
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-6">
            <form method="GET" action="<?php echo e(route('audit.index')); ?>" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Cari Deskripsi</label>
                    <input type="text" name="search" value="<?php echo e(request('search')); ?>"
                           placeholder="Cari aksi / deskripsi..."
                           class="w-full rounded-xl border-gray-300 text-sm focus:ring-red-400 focus:border-red-400 shadow-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Aksi</label>
                    <select name="action" class="w-full rounded-xl border-gray-300 text-sm focus:ring-red-400 shadow-sm">
                        <option value="">Semua Aksi</option>
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__currentLoopData = $actions; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $act): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); ?>
                            <option value="<?php echo e($act); ?>" <?php echo e(request('action') == $act ? 'selected' : ''); ?>>
                                <?php echo e($act); ?>

                            </option>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </select>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Tanggal Mulai</label>
                    <input type="date" name="start_date" value="<?php echo e(request('start_date')); ?>"
                           class="w-full rounded-xl border-gray-300 text-sm focus:ring-red-400 shadow-sm">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-gray-600 mb-1.5">Tanggal Akhir</label>
                    <input type="date" name="end_date" value="<?php echo e(request('end_date')); ?>"
                           class="w-full rounded-xl border-gray-300 text-sm focus:ring-red-400 shadow-sm">
                </div>
                <div class="md:col-span-2 lg:col-span-4 flex gap-3">
                    <button type="submit"
                            class="bg-red-600 hover:bg-red-700 text-white px-5 py-2 rounded-xl text-sm font-semibold transition">
                        Filter
                    </button>
                    <a href="<?php echo e(route('audit.index')); ?>"
                       class="bg-gray-100 hover:bg-gray-200 text-gray-700 px-5 py-2 rounded-xl text-sm font-semibold transition">
                        Reset
                    </a>
                </div>
            </form>
        </div>

        
        <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
            <div class="overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="bg-gray-50 border-b border-gray-100">
                            <th class="text-left px-5 py-3.5 text-xs font-bold text-gray-500 uppercase tracking-wider">Waktu</th>
                            <th class="text-left px-5 py-3.5 text-xs font-bold text-gray-500 uppercase tracking-wider">User</th>
                            <th class="text-left px-5 py-3.5 text-xs font-bold text-gray-500 uppercase tracking-wider">Aksi</th>
                            <th class="text-left px-5 py-3.5 text-xs font-bold text-gray-500 uppercase tracking-wider">Modul</th>
                            <th class="text-left px-5 py-3.5 text-xs font-bold text-gray-500 uppercase tracking-wider">Detail</th>
                            <th class="text-left px-5 py-3.5 text-xs font-bold text-gray-500 uppercase tracking-wider">IP</th>
                            <th class="text-left px-5 py-3.5 text-xs font-bold text-gray-500 uppercase tracking-wider">Sumber</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-50">
                        <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php $__empty_1 = true; $__currentLoopData = $logs; $__env->addLoop($__currentLoopData); foreach($__currentLoopData as $log): $__env->incrementLoopIndices(); $loop = $__env->getLastLoop(); $__empty_1 = false; ?>
                        <tr class="hover:bg-gray-50/70 transition-colors">
                            <td class="px-5 py-3.5 whitespace-nowrap">
                                <div class="text-gray-700 font-medium"><?php echo e($log->created_at?->format('d/m/Y')); ?></div>
                                <div class="text-xs text-gray-400"><?php echo e($log->created_at?->format('H:i:s')); ?></div>
                            </td>
                            <td class="px-5 py-3.5">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($log->user): ?>
                                    <div class="font-medium text-gray-700"><?php echo e($log->user->name); ?></div>
                                    <div class="text-xs text-gray-400"><?php echo e($log->user->email); ?></div>
                                <?php else: ?>
                                    <span class="text-gray-400 italic text-xs">System</span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </td>
                            <td class="px-5 py-3.5">
                                <?php
                                    $actionColors = [
                                        'PAYMENT_CONFIRMED' => 'bg-emerald-100 text-emerald-700',
                                        'BILL_CREATED'      => 'bg-sky-100 text-sky-700',
                                        'BILL_DELETED'      => 'bg-red-100 text-red-700',
                                        'BILL_UPDATED'      => 'bg-amber-100 text-amber-700',
                                        'BILL_CANCELLED'    => 'bg-orange-100 text-orange-700',
                                        'PAYMENT_FAILED'    => 'bg-red-100 text-red-600',
                                        'DISCOUNT_APPLIED'  => 'bg-purple-100 text-purple-700',
                                    ];
                                    $color = $actionColors[$log->action] ?? 'bg-gray-100 text-gray-600';
                                ?>
                                <span class="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold <?php echo e($color); ?>">
                                    <?php echo e($log->action); ?>

                                </span>
                            </td>
                            <td class="px-5 py-3.5 text-gray-500 text-xs"><?php echo e($log->module); ?></td>
                            <td class="px-5 py-3.5 max-w-xs">
                                <div class="text-gray-700 text-xs line-clamp-2"><?php echo e($log->description ?? '-'); ?></div>
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($log->auditable_type && $log->auditable_id): ?>
                                    <div class="text-xs text-gray-400 mt-0.5"><?php echo e($log->auditable_type); ?> #<?php echo e($log->auditable_id); ?></div>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </td>
                            <td class="px-5 py-3.5 text-gray-500 text-xs font-mono"><?php echo e($log->ip_address ?? '-'); ?></td>
                            <td class="px-5 py-3.5">
                                <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($log->source): ?>
                                <span class="inline-flex items-center px-2 py-0.5 rounded text-xs font-medium
                                    <?php echo e($log->source === 'MIDTRANS' ? 'bg-blue-50 text-blue-600' :
                                       ($log->source === 'SYSTEM' ? 'bg-gray-100 text-gray-500' : 'bg-indigo-50 text-indigo-600')); ?>">
                                    <?php echo e($log->source); ?>

                                </span>
                                <?php else: ?>
                                    <span class="text-gray-300">—</span>
                                <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                            </td>
                        </tr>
                        <?php endforeach; $__env->popLoop(); $loop = $__env->getLastLoop(); if ($__empty_1): ?>
                        <tr>
                            <td colspan="7" class="px-5 py-12 text-center text-gray-400">
                                <svg class="w-10 h-10 mx-auto mb-3 text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                                </svg>
                                Belum ada log audit
                            </td>
                        </tr>
                        <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
                    </tbody>
                </table>
            </div>

            
            <?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if BLOCK]><![endif]--><?php endif; ?><?php if($logs->hasPages()): ?>
            <div class="px-5 py-4 border-t border-gray-100">
                <?php echo e($logs->links()); ?>

            </div>
            <?php endif; ?><?php if(\Livewire\Mechanisms\ExtendBlade\ExtendBlade::isRenderingLivewireComponent()): ?><!--[if ENDBLOCK]><![endif]--><?php endif; ?>
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
<?php /**PATH /var/www/sistem-tu/resources/views/audit/index.blade.php ENDPATH**/ ?>