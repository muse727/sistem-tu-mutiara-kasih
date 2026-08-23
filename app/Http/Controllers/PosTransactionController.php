<?php

namespace App\Http\Controllers;

use App\Models\BillItem;
use App\Models\PosItem;
use App\Models\PosOrder;
use App\Models\PosOrderItem;
use App\Models\Student;
use App\Models\StudentBill;
use App\Services\FinancialAuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class PosTransactionController extends Controller
{
    /**
     * 1. Tampilkan Halaman Kasir
     */
    public function index()
    {
        // Ambil barang yang stoknya > 0 saja untuk ditampilkan di grid
        $items = PosItem::where('stock', '>', 0)
                        ->orderBy('name')
                        ->get();

        // Include kelas_id so we can resolve kelas name in the view
        $students = Student::with('kelas')
                           ->where('status', 'active')
                           ->orderBy('name')
                           ->get(['id', 'name', 'nis', 'kelas_id'])
                           ->map(fn($s) => [
                               'id'         => $s->id,
                               'name'       => $s->name,
                               'nis'        => $s->nis,
                               'class_name' => optional($s->kelas)->nama_kelas ?? '-',
                           ]);

        return view('pos.transaction.index', compact('items', 'students'));
    }

    /**
     * 2. Proses Checkout (Cash & Online/Hutang)
     */
    public function store(Request $request)
    {
        // Validasi Input dari Frontend
        $request->validate([
            'cart'             => 'required|array|min:1',
            'cart.*.id'        => 'required|exists:pos_items,id',
            'cart.*.qty'       => 'required|integer|min:1',
            'payment_amount'   => 'required|numeric|min:0',
            'student_id'       => 'nullable|exists:students,id',
        ]);

        try {
            DB::beginTransaction();

            // A. Rekalkulasi total_amount di SERVER dari harga DB.
            // Tidak percaya nilai total_amount dari frontend — bisa dimanipulasi.
            $serverTotal = 0;
            foreach ($request->cart as $cartItem) {
                $item = PosItem::find($cartItem['id']);
                if ($item) {
                    $serverTotal += $item->price * $cartItem['qty'];
                }
            }

            if ($serverTotal <= 0) {
                throw new \Exception('Total transaksi tidak valid.');
            }

            // A. Tentukan Status & Hitung Angka
            // Jika uang yg dimasukkan >= total server, maka LUNAS (Cash).
            $isCash = (float) $request->payment_amount >= $serverTotal;

            // --- LOGIC PENTING: VALIDASI HUTANG ---
            // Kalau bukan Cash (berarti Hutang/QRIS), WAJIB pilih siswa.
            if (!$isCash && empty($request->student_id)) {
                throw new \Exception('Untuk pembayaran Hutang/QRIS, WAJIB memilih nama siswa!');
            }

            $statusPayment    = $isCash ? 'PAID' : 'UNPAID';
            $statusRedemption = $isCash ? 'COMPLETED' : 'PENDING';
            $changeAmount     = $isCash ? ((float) $request->payment_amount - $serverTotal) : 0;

            // B. Simpan Header Transaksi (PosOrder) — pakai $serverTotal bukan request->total_amount
            $order = PosOrder::create([
                'user_id'           => Auth::id(),
                'student_id'        => $request->student_id,
                'transaction_code'  => 'TRX-' . time() . Str::upper(Str::random(3)),
                'total_amount'      => $serverTotal,
                'payment_amount'    => (float) $request->payment_amount,
                'change_amount'     => $changeAmount,
                'payment_status'    => $statusPayment,
                'redemption_status' => $statusRedemption,
                'qr_token'          => Str::uuid(),
            ]);

            // C. Simpan Detail Item & Kurangi Stok
            $orderItems = []; // simpan untuk BillItem nanti
            foreach ($request->cart as $cartItem) {
                $itemDB = PosItem::lockForUpdate()->find($cartItem['id']);

                if ($itemDB->stock < $cartItem['qty']) {
                    throw new \Exception("Stok barang '{$itemDB->name}' tidak cukup! Sisa: {$itemDB->stock}");
                }

                $itemDB->decrement('stock', $cartItem['qty']);

                PosOrderItem::create([
                    'pos_order_id'         => $order->id,
                    'pos_item_id'          => $itemDB->id,
                    'quantity'             => $cartItem['qty'],
                    'price_at_transaction' => $itemDB->price,
                ]);

                $orderItems[] = ['item' => $itemDB, 'quantity' => $cartItem['qty']];
            }

            // D. Opsi C: buat StudentBill type KANTIN untuk hutang siswa.
            // Hanya untuk transaksi UNPAID dengan student_id.
            // student_bills jadi satu-satunya sumber kebenaran piutang siswa.
            if (! $isCash && $request->student_id) {
                $bill = StudentBill::create([
                    'pos_order_id' => $order->id,
                    'student_id'   => $request->student_id,
                    'name'         => 'Kantin/Koperasi ' . now()->format('d/m/Y'),
                    'type'         => 'KANTIN',
                    'amount'       => $serverTotal,
                    'status'       => 'UNPAID',
                    'created_by'   => Auth::id(),
                ]);

                foreach ($orderItems as $oi) {
                    BillItem::create([
                        'student_bill_id' => $bill->id,
                        'item_name'       => $oi['item']->name,
                        'quantity'        => $oi['quantity'],
                        'price'           => $oi['item']->price,
                        'subtotal'        => $oi['item']->price * $oi['quantity'],
                    ]);
                }

                FinancialAuditLogger::billCreated($bill);
            }

            DB::commit();

            return response()->json([
                'success'          => true,
                'message'          => 'Transaksi Berhasil!',
                'change'           => number_format($changeAmount, 0, ',', '.'),
                'trx_id'           => $order->id,
                'transaction_code' => $order->transaction_code,
            ]);

        } catch (\Exception $e) {
            DB::rollBack();
            return response()->json([
                'success' => false,
                'message' => 'Gagal: ' . $e->getMessage()
            ], 400);
        }
    }

    /**
     * 3. Cetak Struk (Thermal Style)
     */
    public function printStruk($id)
    {
        $transaction = PosOrder::with(['items.item', 'user', 'student'])->findOrFail($id);
        $school      = DB::table('school_settings')->pluck('value', 'key');

        return view('pos.transaction.print', compact('transaction', 'school'));
    }
}