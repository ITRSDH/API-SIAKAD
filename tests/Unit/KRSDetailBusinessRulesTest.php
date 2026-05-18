<?php

namespace Tests\Unit;

use App\Models\Akademik\KRSDetail;
use Tests\TestCase;

class KRSDetailBusinessRulesTest extends TestCase
{
    public function test_lulus_with_complete_final_score_is_counted_in_khs(): void
    {
        $detail = new KRSDetail([
            'status' => KRSDetail::STATUS_LULUS,
            'nilai_akhir' => 82.5,
            'nilai_huruf' => 'A-',
            'bobot_nilai' => 3.75,
        ]);

        $this->assertTrue($detail->isFinalScored());
        $this->assertTrue($detail->isCountedInKhs());
    }

    public function test_tidak_lulus_with_complete_final_score_is_counted_in_khs(): void
    {
        $detail = new KRSDetail([
            'status' => KRSDetail::STATUS_TIDAK_LULUS,
            'nilai_akhir' => 40,
            'nilai_huruf' => 'D',
            'bobot_nilai' => 1.00,
        ]);

        $this->assertTrue($detail->isFinalScored());
        $this->assertTrue($detail->isCountedInKhs());
    }

    public function test_drop_is_not_final_scored_and_not_counted_in_khs(): void
    {
        $detail = new KRSDetail([
            'status' => KRSDetail::STATUS_DROP,
            'nilai_akhir' => null,
            'nilai_huruf' => null,
            'bobot_nilai' => null,
        ]);

        $this->assertFalse($detail->isFinalScored());
        $this->assertFalse($detail->isCountedInKhs());
    }

    public function test_terdaftar_is_not_final_scored_and_not_counted_in_khs(): void
    {
        $detail = new KRSDetail([
            'status' => KRSDetail::STATUS_TERDAFTAR,
            'nilai_akhir' => null,
            'nilai_huruf' => null,
            'bobot_nilai' => null,
        ]);

        $this->assertFalse($detail->isFinalScored());
        $this->assertFalse($detail->isCountedInKhs());
    }
}
