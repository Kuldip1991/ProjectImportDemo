<?php

namespace App\Services;
use App\Models\Discount;
use App\Models\DiscountAudit;
use App\Models\UserDiscount;
use App\Events\{DiscountApplied,DiscountAssigned,DiscountRevoked};
class DiscountService
{
    public function assign(int $userId, int $discountId): UserDiscount
    {
        $userDiscount = UserDiscount::firstOrCreate([
            'user_id' => $userId,
            'discount_id' => $discountId,
        ]);

        if (config('user-discounts.fire_events')) {
            event(new DiscountAssigned($userDiscount));
        }

        return $userDiscount;
    }

    public function revoke(int $userId, int $discountId): bool
    {
        $userDiscount = UserDiscount::where('user_id', $userId)
            ->where('discount_id', $discountId)
            ->first();

        if ($userDiscount) {
            $userDiscount->delete();

            if (config('user-discounts.fire_events')) {
                event(new DiscountRevoked($userId, $discountId));
            }

            return true;
        }

        return false;
    }

    public function eligibleFor(int $userId)
    {
        return Discount::where('active', true)
            ->where(function ($query) {
                $query->whereNull('expires_at')
                      ->orWhere('expires_at', '>', now());
            })
            ->whereHas('userDiscounts', fn($q) => $q->where('user_id', $userId))
            ->get();
    }

    public function apply(int $userId, float $amount, string $context): array
    {
        $discounts = $this->eligibleFor($userId);
        $stackingOrder = config('user-discounts.stacking_order', 'desc');
        $maxCap = config('user-discounts.max_percentage_cap', 100);
        $rounding = config('user-discounts.rounding', 'round');

        $discounts = $stackingOrder === 'asc'
            ? $discounts->sortBy('percentage')
            : $discounts->sortByDesc('percentage');

        $totalDiscount = 0;
        $applied = [];

        foreach ($discounts as $discount) {
            $userDiscount = UserDiscount::where('user_id', $userId)
                ->where('discount_id', $discount->id)
                ->first();

            if (!$userDiscount || ($discount->max_usage && $userDiscount->usage_count >= $discount->max_usage)) {
                continue;
            }

            $discountAmount = $amount * ($discount->percentage / 100);
            $discountAmount = match ($rounding) {
                'floor' => floor($discountAmount),
                'ceil' => ceil($discountAmount),
                default => round($discountAmount),
            };

            $totalDiscount += $discountAmount;

            $applied[] = [
                'discount_id' => $discount->id,
                'code' => $discount->code,
                'discount_amount' => $discountAmount,
            ];

            $userDiscount->increment('usage_count');

            DiscountAudit::create([
                'user_id' => $userId,
                'discount_id' => $discount->id,
                'context' => $context,
                'amount' => $discountAmount,
            ]);

            if ($totalDiscount >= ($amount * $maxCap / 100)) {
                break;
            }
        }

        $finalAmount = max(0, $amount - $totalDiscount);

        if (config('user-discounts.fire_events')) {
            event(new DiscountApplied($userId, $amount, $finalAmount, $applied));
        }

        return [
            'original_amount' => $amount,
            'discount_amount' => $totalDiscount,
            'final_amount' => $finalAmount,
            'applied_discounts' => $applied,
        ];
    }

}