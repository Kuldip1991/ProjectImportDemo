<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;
use App\Models\User;
use App\Models\Discount;
use App\Models\DiscountAudit;
use App\Models\UserDiscount;
use App\Services\DiscountService;


class DiscountServiceTest extends TestCase
{
  
    use RefreshDatabase;

    public function test_discount_application()
    {
        // $user = User::factory()->create();
       // dd($user);
        $discount1 = Discount::create([
            'code' => 'WELCOME10',
            'percentage' => 10,
            'active' => true,
            'expires_at' => now()->addDays(5),
            'max_usage' => 5,
        ]);

        $discount2 = Discount::create([
            'code' => 'LOYALTY5',
            'percentage' => 5,
            'active' => true,
            'expires_at' => now()->addDays(5),
            'max_usage' => 5,
        ]);

        UserDiscount::create([
            'user_id' => 1,
            'discount_id' => $discount1->id,
            'usage_count' => 0,
        ]);

        UserDiscount::create([
            'user_id' => 1,
            'discount_id' => $discount2->id,
            'usage_count' => 0,
        ]);

        $service = new DiscountService();
        $result = $service->apply(1, 100.00, 'ORDER_ABC');

        $this->assertEquals(100.00, $result['original_amount']);
        $this->assertEquals(15.00, $result['discount_amount']);
        $this->assertEquals(85.00, $result['final_amount']);
        $this->assertCount(2, $result['applied_discounts']);
    }


    // public function test_example(): void
    // {
    //     $response = $this->get('/');

    //     $response->assertStatus(200);
    // }
}
