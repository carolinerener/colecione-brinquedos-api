<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Services\MercadoPagoService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class WebhookController extends Controller
{
    public function __construct(
        private MercadoPagoService $mercadoPagoService
    ) {}

    /**
     * Recebe notificações do MercadoPago quando um pagamento muda de status.
     * O MercadoPago manda POST com { type: 'payment', data: { id: '123' } } e espera 200.
     * Se retornarmos qualquer coisa diferente de 2xx, ele tenta de novo até 5 vezes.
     */
    public function mercadopago(Request $request): JsonResponse
    {
        Log::info('Webhook MercadoPago recebido', [
            'payload' => $request->all(),
            'ip' => $request->ip(),
        ]);

        $type = $request->input('type');
        $paymentId = $request->input('data.id');

        if ($type !== 'payment' || !$paymentId) {
            return response()->json(['message' => 'Notification ignored'], 200);
        }

        $pagamento = $this->mercadoPagoService->buscarPagamento($paymentId);

        if (!$pagamento) {
            Log::warning('Pagamento não encontrado ou erro ao buscar', [
                'payment_id' => $paymentId,
            ]);
            return response()->json(['message' => 'Payment not found'], 200);
        }

        $orderId = $pagamento['external_reference'] ?? null;

        if (!$orderId) {
            Log::warning('Pagamento sem external_reference', [
                'payment_id' => $paymentId,
            ]);
            return response()->json(['message' => 'Missing reference'], 200);
        }

        $order = Order::find($orderId);

        if (!$order) {
            Log::warning('Pedido não encontrado no banco', [
                'payment_id' => $paymentId,
                'external_reference' => $orderId,
            ]);
            return response()->json(['message' => 'Order not found'], 200);
        }

        $novoStatus = $this->mapearStatus($pagamento['status']);

        if ($order->status === 'paid' && $novoStatus !== 'paid') {
            Log::warning('Tentativa de reverter pedido já pago', [
                'order_id' => $order->id,
                'current_status' => $order->status,
                'attempted_status' => $novoStatus,
            ]);
            return response()->json(['message' => 'Order already paid'], 200);
        }

        $dadosAtualizacao = [
            'status' => $novoStatus,
            'payment_id' => $pagamento['id'],
            'payment_type' => $pagamento['payment_type_id'],
        ];

        if ($novoStatus === 'paid') {
            $dadosAtualizacao['paid_at'] = $pagamento['date_approved'] ?? now();
        }

        $order->update($dadosAtualizacao);

        Log::info('Pedido atualizado via webhook', [
            'order_id' => $order->id,
            'payment_id' => $pagamento['id'],
            'new_status' => $novoStatus,
        ]);

        return response()->json(['message' => 'OK'], 200);
    }

    /**
     * Mapeia o status do MercadoPago pro status interno do pedido.
     */
    private function mapearStatus(string $mpStatus): string
    {
        return match ($mpStatus) {
            'approved' => 'paid',
            'rejected', 'cancelled' => 'cancelled',
            'pending', 'in_process', 'authorized' => 'pending',
            'refunded', 'charged_back' => 'refunded',
            default => 'pending',
        };
    }
}