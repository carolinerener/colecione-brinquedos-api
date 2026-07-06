<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreCouponRequest;
use App\Http\Requests\UpdateCouponRequest;
use App\Models\Coupon;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class CouponController extends Controller
{
    /**
     * Lista paginada de cupons (admin).
     */
    public function index(): JsonResponse
    {
        $coupons = Coupon::orderBy('created_at', 'desc')->paginate(10);
        return response()->json($coupons);
    }

    /**
     * Cria um novo cupom (admin).
     */
    public function store(StoreCouponRequest $request): JsonResponse
    {
        $coupon = Coupon::create($request->validated());
        return response()->json($coupon, 201);
    }

    /**
     * Exibe detalhes de um cupom (admin).
     */
    public function show(Coupon $coupon): JsonResponse
    {
        return response()->json($coupon);
    }

    /**
     * Atualiza um cupom (admin).
     */
    public function update(UpdateCouponRequest $request, Coupon $coupon): JsonResponse
    {
        $coupon->update($request->validated());
        return response()->json($coupon);
    }

    /**
     * Deleta um cupom (admin).
     */
    public function destroy(Coupon $coupon): JsonResponse
    {
        $coupon->delete();
        return response()->json(null, 204);
    }

    /**
     * Valida um cupom pra um pedido (cliente logado).
     * Não incrementa o contador de usos — isso acontece só quando o pedido é finalizado.
     */
    public function validateCoupon(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:50'],
            'order_total' => ['required', 'numeric', 'min:0.01'],
        ], [
            'code.required' => 'O código do cupom é obrigatório.',
            'order_total.required' => 'O valor total do pedido é obrigatório.',
            'order_total.numeric' => 'O valor total do pedido deve ser numérico.',
            'order_total.min' => 'O valor total do pedido deve ser maior que zero.',
        ]);

        $coupon = Coupon::where('code', $validated['code'])->first();

        if (!$coupon) {
            Log::warning('Tentativa de uso de cupom inexistente', [
                'user_id' => $request->user()->id,
                'code' => $validated['code'],
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'valid' => false,
                'message' => 'Cupom não encontrado ou inválido.',
            ], 404);
        }

        $check = $coupon->isValid((float) $validated['order_total']);

        if (!$check['valid']) {
            Log::warning('Tentativa de uso de cupom inválido', [
                'user_id' => $request->user()->id,
                'coupon_id' => $coupon->id,
                'code' => $coupon->code,
                'reason' => $check['message'],
                'ip' => $request->ip(),
            ]);

            return response()->json([
                'valid' => false,
                'message' => $check['message'],
            ], 422);
        }

        $orderTotal = (float) $validated['order_total'];
        $discount = $coupon->calculateDiscount($orderTotal);
        $finalTotal = round($orderTotal - $discount, 2);

        return response()->json([
            'valid' => true,
            'message' => $check['message'],
            'coupon' => [
                'code' => $coupon->code,
                'type' => $coupon->type,
                'value' => $coupon->value,
            ],
            'discount' => $discount,
            'final_total' => $finalTotal,
        ]);
    }
}