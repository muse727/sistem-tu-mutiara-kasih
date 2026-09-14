<?php

namespace Tests\Feature;

use App\Models\PosBundle;
use App\Models\PosBundleItem;
use App\Models\PosItem;
use App\Models\Student;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * POS bundle bill generation.
 *
 * Preserved from the removed Phase12RegistrasiSiswaTest (test L1). The PPDB
 * module is out of scope for the current TU architecture, but bundle bill
 * generation is a CURRENT, valid flow (pos.bundles.generateBills) so its
 * behaviour is preserved here against the current route.
 */
class PosBundleGenerateBillsTest extends TestCase
{
    use RefreshDatabase;

    public function test_bundle_can_be_generated_for_active_student(): void
    {
        $tu    = User::factory()->create(['role' => 'tu']);
        $siswa = Student::factory()->create(['status' => 'active']);

        $item = PosItem::create([
            'name'      => 'SPP Bulanan',
            'price'     => 250000,
            'category'  => 'SPP',
            'stock'     => 10,
            'is_active' => true,
        ]);
        $bundle = PosBundle::create([
            'name'      => 'Paket Awal Masuk',
            'is_active' => true,
        ]);
        PosBundleItem::create([
            'pos_bundle_id' => $bundle->id,
            'pos_item_id'   => $item->id,
            'quantity'      => 1,
        ]);

        $this->actingAs($tu)->post(
            route('pos.bundles.generateBills', $bundle->id),
            ['student_ids' => [$siswa->id]]
        );

        $this->assertDatabaseHas('student_bills', [
            'student_id' => $siswa->id,
        ]);
    }
}
