<x-app-layout>

    <div class="bg-gradient-to-br from-slate-50 to-blue-50 min-h-screen pb-12">

        {{-- HEADER (mengikuti pola /bills) --}}
        <div class="bg-white border-b border-slate-200 px-8 py-8 shadow-lg relative overflow-hidden">
            <div class="absolute inset-0 bg-gradient-to-r from-blue-50/50 to-transparent pointer-events-none"></div>

            <div class="flex flex-col md:flex-row justify-between items-center gap-6 relative z-10">
                <div class="flex items-center gap-4">
                    <div class="relative">
                        <div class="absolute inset-0 bg-gradient-to-r from-[#F56C3E] to-blue-400 rounded-xl blur-lg opacity-30"></div>
                        <div class="relative bg-gradient-to-br from-[#F56C3E] to-blue-600 p-3 rounded-xl shadow-lg">
                            <svg class="w-8 h-8 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                        </div>
                    </div>
                    <div>
                        <h2 class="text-3xl font-bold text-slate-800 tracking-tight">Daily Close SPP</h2>
                        <p class="text-sm text-slate-500 font-medium mt-1">Tutup batch settlement SPP harian ke sistem Finance.</p>
                    </div>
                </div>
            </div>
        </div>

        <div class="px-8 mt-8 max-w-5xl mx-auto space-y-6">

            @if (! $batch)
                {{-- EMPTY STATE --}}
                <div class="bg-white rounded-2xl border border-slate-200 shadow-lg p-12 text-center">
                    <div class="mx-auto w-14 h-14 rounded-full bg-slate-100 flex items-center justify-center mb-4">
                        <svg class="w-7 h-7 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 11V7a4 4 0 118 0m-4 8v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2z"/></svg>
                    </div>
                    <h3 class="text-lg font-bold text-slate-800">Tidak ada batch yang memenuhi syarat</h3>
                    <p class="text-sm text-slate-500 mt-2">Saat ini tidak ada batch settlement SPP berstatus DRAFT / READY_TO_CLOSE untuk ditutup.</p>
                </div>
            @else
                {{-- BATCH SUMMARY CARD --}}
                <div class="bg-white rounded-2xl border border-slate-200 shadow-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100 flex items-center justify-between">
                        <h3 class="text-base font-bold text-slate-800">Batch yang Akan Ditutup</h3>
                        <span class="inline-flex items-center px-3 py-1 rounded-full text-xs font-bold
                            {{ $batch['status'] === 'READY_TO_CLOSE' ? 'bg-amber-100 text-amber-700' : 'bg-sky-100 text-sky-700' }}">
                            {{ $batch['status'] }}
                        </span>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6 p-6">
                        <div>
                            <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Periode</p>
                            <p class="text-sm font-semibold text-slate-800 mt-1">{{ $batch['period_kind'] }} · {{ $batch['period_start'] }} s/d {{ $batch['period_end'] }}</p>
                        </div>
                        <div class="sm:col-span-2">
                            <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Batch / Referensi</p>
                            <p class="text-xs font-mono text-slate-700 mt-1 break-all">{{ $batch['batch_id'] }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Total</p>
                            <p class="text-2xl font-extrabold text-slate-800 mt-1">Rp {{ number_format((float) $batch['total_amount'], 0, ',', '.') }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Pembayaran</p>
                            <p class="text-2xl font-extrabold text-slate-800 mt-1">{{ $batch['payment_count'] }}</p>
                        </div>
                        <div>
                            <p class="text-xs font-medium text-slate-400 uppercase tracking-wide">Tagihan (StudentBill)</p>
                            <p class="text-2xl font-extrabold text-slate-800 mt-1">{{ $batch['bill_count'] }}</p>
                        </div>
                    </div>
                </div>

                {{-- BREAKDOWN (read-only, sebagaimana disediakan backend) --}}
                @if (! empty($batch['breakdown']))
                <div class="bg-white rounded-2xl border border-slate-200 shadow-lg overflow-hidden">
                    <div class="px-6 py-4 border-b border-slate-100">
                        <h3 class="text-base font-bold text-slate-800">Rincian Metode Pembayaran</h3>
                    </div>
                    <div class="overflow-x-auto">
                        <table class="w-full text-left border-collapse">
                            <thead>
                                <tr class="bg-slate-50 border-b border-slate-200 text-[11px] font-semibold uppercase tracking-wide text-slate-500">
                                    <th class="px-6 py-3">Metode</th>
                                    <th class="px-6 py-3">Provider</th>
                                    <th class="px-6 py-3 text-right">Jumlah</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($batch['breakdown'] as $line)
                                <tr class="border-b border-slate-100 hover:bg-slate-50">
                                    <td class="px-6 py-3 text-sm font-medium text-slate-800">{{ strtoupper((string) ($line['payment_method'] ?? '-')) }}</td>
                                    <td class="px-6 py-3 text-sm text-slate-600">{{ $line['provider'] ?? '—' }}</td>
                                    <td class="px-6 py-3 text-sm font-semibold text-right text-slate-800">Rp {{ number_format((float) ($line['amount'] ?? 0), 0, ',', '.') }}</td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                </div>
                @endif

                {{-- CLOSE ACTION --}}
                <div class="bg-white rounded-2xl border border-slate-200 shadow-lg p-6">
                    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                        <p class="text-xs leading-relaxed text-slate-500 max-w-xl">
                            Penutupan bersifat <strong class="text-slate-700">permanen</strong>. Batch yang sudah ditutup
                            (CLOSED) tidak dapat diubah atau dibuka kembali. Pembayaran yang masuk setelah penutupan akan
                            masuk ke batch overflow untuk periode yang sama.
                        </p>

                        @if ($canClose)
                        <form action="{{ route('spp-daily-close.close') }}" method="POST" class="close-form"
                              data-period="{{ $batch['period_start'] }} s/d {{ $batch['period_end'] }}"
                              data-batch="{{ $batch['batch_id'] }}"
                              data-total="Rp {{ number_format((float) $batch['total_amount'], 0, ',', '.') }}"
                              data-counts="{{ $batch['payment_count'] }} pembayaran · {{ $batch['bill_count'] }} tagihan">
                            @csrf
                            <button type="button" class="btn-close relative group">
                                <div class="absolute inset-0 bg-gradient-to-r from-[#F56C3E] to-rose-500 rounded-xl blur opacity-75 group-hover:opacity-100 transition-opacity"></div>
                                <div class="relative flex items-center gap-2 bg-gradient-to-r from-[#F56C3E] to-rose-500 text-white px-6 py-3 rounded-xl font-semibold text-sm shadow-lg transition-all transform group-hover:-translate-y-0.5">
                                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                                    Tutup Batch (Daily Close)
                                </div>
                            </button>
                        </form>
                        @else
                        <span class="inline-flex items-center gap-2 px-4 py-2 rounded-xl bg-slate-100 text-slate-500 text-sm font-semibold">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/></svg>
                            Hanya Admin / TU yang dapat menutup batch
                        </span>
                        @endif
                    </div>
                </div>
            @endif

        </div>
    </div>

    {{-- KONFIRMASI (pola SweetAlert yang sama seperti /bills) --}}
    <script>
        document.querySelectorAll('.btn-close').forEach(btn => {
            btn.addEventListener('click', function () {
                const form = this.closest('.close-form');
                Swal.fire({
                    title: 'Konfirmasi Daily Close',
                    html:
                        '<div style="text-align:left;font-size:0.875rem;line-height:1.6">' +
                        '<div style="display:flex;justify-content:space-between;margin-bottom:4px"><span style="color:#64748b">Periode</span><strong>' + form.dataset.period + '</strong></div>' +
                        '<div style="display:flex;justify-content:space-between;margin-bottom:4px;gap:12px"><span style="color:#64748b;flex-shrink:0">Referensi</span><span style="font-family:monospace;font-size:0.75rem;word-break:break-all;text-align:right">' + form.dataset.batch + '</span></div>' +
                        '<div style="display:flex;justify-content:space-between;margin-bottom:4px"><span style="color:#64748b">Total</span><strong style="color:#F56C3E">' + form.dataset.total + '</strong></div>' +
                        '<div style="display:flex;justify-content:space-between"><span style="color:#64748b">Pembayaran / Tagihan</span><strong>' + form.dataset.counts + '</strong></div>' +
                        '<p style="margin-top:12px;color:#64748b">Tindakan ini tidak dapat dibatalkan.</p>' +
                        '</div>',
                    icon: 'warning',
                    showCancelButton: true,
                    confirmButtonColor: '#F56C3E',
                    cancelButtonColor: '#777777',
                    confirmButtonText: 'Ya, Tutup Batch',
                    cancelButtonText: 'Batal',
                    background: '#ffffff',
                    backdrop: 'rgba(245, 108, 62, 0.1)'
                }).then((result) => {
                    if (result.isConfirmed) form.submit();
                });
            });
        });
    </script>

</x-app-layout>
