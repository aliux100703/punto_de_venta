<?php

namespace Tests\Feature;

use App\Models\Sale;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DashboardPeriodTest extends TestCase
{
    use RefreshDatabase;

    public function test_chart_periods_use_real_sales_and_respect_the_sellers_scope(): void
    {
        $seller = User::factory()->create();
        $other = User::factory()->administrator()->create();
        foreach ([[$seller, 100, 0], [$seller, 80, 1], [$seller, 40, 20], [$other, 5000, 0]] as $index => [$user, $total, $daysAgo]) {
            $sale = Sale::create(['folio' => 'PERIOD-'.$index, 'seller_id' => $user->id, 'total' => $total, 'status' => 'COMPLETADA']);
            $sale->forceFill(['created_at' => today()->subDays($daysAgo)->setTime(12, 0)])->save();
        }
        $this->actingAs($seller)->get('/dashboard?period=week')->assertOk()
            ->assertViewHas('periodRevenue', 180.0)->assertViewHas('periodOperations', 2)
            ->assertViewHas('dailySales', fn ($days) => $days->count() === 7 && $days->sum('amount') === 180.0)
            ->assertViewHas('expenses', null);
        $this->get('/dashboard?period=month')->assertOk()->assertViewHas('periodRevenue', 220.0)
            ->assertViewHas('dailySales', fn ($days) => $days->count() === 30);
        $this->actingAs($other)->get('/dashboard?period=week')->assertOk()->assertViewHas('periodRevenue', 5180.0);
    }

    public function test_an_empty_period_has_zero_values_and_invalid_periods_are_rejected(): void
    {
        $this->actingAs(User::factory()->create())->get('/dashboard')->assertOk()->assertViewHas('periodRevenue', 0.0)
            ->assertViewHas('dailySales', fn ($days) => $days->sum('amount') === 0.0);
        $this->get('/dashboard?period=invalid')->assertSessionHasErrors('period');
    }
}
