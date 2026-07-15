<?php

namespace App\Services;

use Exception;
use Illuminate\Support\Facades\Log;
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
                'back_urls' => [
                    'success' => config('app.frontend_url') . '/checkout/sucesso',
                    'failure' => config('app.frontend_url') . '/checkout/falha',
                    'pending' => config('app.frontend_url') . '/checkout/pendente',
                ],
                'auto_return' => 'approved',
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
}