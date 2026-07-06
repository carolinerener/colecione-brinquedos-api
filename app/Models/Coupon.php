<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Coupon extends Model
{
    protected $fillable = [
        'code',
        'type',
        'value',
        'max_uses',
        'used_times',
        'min_order_value',
        'active',
        'expires_at',
    ];

    protected $casts = [
        'value' => 'decimal:2',
        'min_order_value' => 'decimal:2',
        'max_uses' => 'integer',
        'used_times' => 'integer',
        'active' => 'boolean',
        'expires_at' => 'datetime',
    ];

    public function orders()
    {
        return $this->hasMany(Order::class);
    }

    /**
     * Verifica se o cupom pode ser aplicado a um pedido de determinado valor.
     * Retorna array com status e mensagem, pra ser usado direto na resposta da API.
     */
    public function isValid(float $orderTotal): array
    {
        if (!$this->active) {
            return ['valid' => false, 'message' => 'Cupom inativo.'];
        }

        if ($this->expires_at && $this->expires_at->isPast()) {
            return ['valid' => false, 'message' => 'Cupom expirado.'];
        }

        if ($this->max_uses !== null && $this->used_times >= $this->max_uses) {
            return ['valid' => false, 'message' => 'Cupom atingiu o limite de usos.'];
        }

        if ($this->min_order_value !== null && $orderTotal < $this->min_order_value) {
            return [
                'valid' => false,
                'message' => 'Valor mínimo do pedido: R$ ' . number_format($this->min_order_value, 2, ',', '.'),
            ];
        }

        return ['valid' => true, 'message' => 'Cupom válido.'];
    }

    /**
     * Calcula o valor do desconto sobre o total do pedido.
     * Não passa do próprio total (evita desconto maior que o pedido em cupons fixos).
     */
    public function calculateDiscount(float $orderTotal): float
    {
        if ($this->type === 'percentage') {
            $discount = $orderTotal * ($this->value / 100);
        } else {
            $discount = (float) $this->value;
        }

        return round(min($discount, $orderTotal), 2);
    }
}