<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Services\DiscountService;

class DiscountController extends Controller
{
     public function __construct(protected DiscountService $discountService) {}

    public function apply(Request $request)
    {
        $request->validate([
            'amount' => 'required|numeric|min:0',
            'context' => 'required|string',
        ]);

        $userId = auth()->id(); // or pass manually
        $amount = $request->input('amount');
        $context = $request->input('context');

        $result = $this->discountService->apply($userId, $amount, $context);
        return response()->json([
            'message' => 'Discounts applied successfully',
            'data' => $result,
        ]);
    }

}
