<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;
use MercadoPago\Client\Payment\PaymentClient;
use MercadoPago\Client\Preference\PreferenceClient;
use MercadoPago\Exceptions\MPApiException;
use MercadoPago\MercadoPagoConfig;

class MercadoPagoService
{
    public function __construct()
    {
        MercadoPagoConfig::setAccessToken(config('services.mercadopago.access_token'));
    }

    /**
     * Cria uma Preference no MercadoPago pra um pedido.
     *
     * @param array $items Itens do pedido (formato: [['title' => 'Produto X', 'quantity' => 2, 'unit_price' => 29.90], ...])
     * @param int $orderId ID do pedido no nosso banco (usado como external_reference)
     * @param string $payerEmail Email do cliente
     * @return array Retorna ['id' => 'MP-preference-id', 'init_point' => 'https://mercadopago.com/checkout/...']
     * @throws Exception Em caso de falha na comunicação com o MercadoPago
     */
    public function criarPreference(array $items, int $orderId, string $payerEmail): array
    {
        try {
            $client = new PreferenceClient();

            $preference = $client->create([
                'items' => $items,
                'payer' => [
                    'email' => $payerEmail,
                ],
                'external_reference' => (string) $orderId,
                'notification_url' => config('services.mercadopago.webhook_url'),
                'back_urls' => [
                    'success' => config('app.frontend_url') . '/checkout/sucesso',
                    'failure' => config('app.frontend_url') . '/checkout/falha',
                    'pending' => config('app.frontend_url') . '/checkout/pendente',
                ],
                'statement_descriptor' => 'COLECIONEBRINQUEDOS',
            ]);

            return [
                'id' => $preference->id,
                'init_point' => $preference->init_point,
                'sandbox_init_point' => $preference->sandbox_init_point,
            ];
        } catch (MPApiException $e) {
            Log::error('Erro ao criar Preference no MercadoPago', [
                'order_id' => $orderId,
                'status' => $e->getApiResponse()->getStatusCode(),
                'response' => $e->getApiResponse()->getContent(),
            ]);
            throw new Exception('Erro ao criar preferência de pagamento. Tente novamente.');
        } catch (Exception $e) {
            Log::error('Erro inesperado ao criar Preference', [
                'order_id' => $orderId,
                'message' => $e->getMessage(),
            ]);
            throw new Exception('Erro ao criar preferência de pagamento.');
        }
    }

    /**
     * Busca detalhes de um pagamento no MercadoPago pelo ID.
     * Usado no webhook pra confirmar o status real (nunca confia na notificação sozinha).
     *
     * @param string $paymentId ID do pagamento no MercadoPago
     * @return array|null Retorna array com dados do pagamento, ou null se não encontrado
     */
    public function buscarPagamento(string $paymentId): ?array
    {
        try {
            $client = new PaymentClient();
            $payment = $client->get($paymentId);

            return [
                'id' => $payment->id,
                'status' => $payment->status,
                'status_detail' => $payment->status_detail,
                'payment_type_id' => $payment->payment_type_id,
                'external_reference' => $payment->external_reference,
                'transaction_amount' => $payment->transaction_amount,
                'date_approved' => $payment->date_approved,
            ];
        } catch (MPApiException $e) {
            Log::warning('Erro ao buscar pagamento no MercadoPago', [
                'payment_id' => $paymentId,
                'status' => $e->getApiResponse()->getStatusCode(),
                'response' => $e->getApiResponse()->getContent(),
            ]);
            return null;
        } catch (Exception $e) {
            Log::error('Erro inesperado ao buscar pagamento', [
                'payment_id' => $paymentId,
                'message' => $e->getMessage(),
            ]);
            return null;
        }
    }
}