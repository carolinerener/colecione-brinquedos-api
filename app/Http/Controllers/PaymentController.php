<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\MercadoPagoService;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(
        private MercadoPagoService $mercadoPagoService
    ) {}

    /**
     * Cria uma Preference no MercadoPago pra um pedido existente.
     * O cliente precisa estar autenticado e ser o dono do pedido.
     */
    public function criarPreference(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'order_id' => ['required', 'integer', 'exists:orders,id'],
        ], [
            'order_id.required' => 'O ID do pedido é obrigatório.',
            'order_id.integer' => 'O ID do pedido deve ser um número.',
            'order_id.exists' => 'Pedido não encontrado.',
        ]);

        $order = Order::with('items.product')->findOrFail($validated['order_id']);

        if ($order->user_id !== $request->user()->id) {
            return response()->json([
                'message' => 'Você não tem permissão pra pagar este pedido.',
            ], 403);
        }

        if ($order->status !== 'pending') {
            return response()->json([
                'message' => 'Este pedido não está mais aguardando pagamento.',
            ], 422);
        }

      $items = $order->items->map(function ($item) {
         return [
           'title' => $item->product->name,
           'quantity' => (int) $item->quantity,
           'unit_price' => (float) $item->unit_price,
           'currency_id' => 'BRL',
               ];
        })->toArray();

        try {
            $preference = $this->mercadoPagoService->criarPreference(
                items: $items,
                orderId: $order->id,
                payerEmail: $request->user()->email,
            );

            return response()->json($preference);
        } catch (Exception $e) {
            return response()->json([
                'message' => $e->getMessage(),
            ], 500);
        }
    }
}