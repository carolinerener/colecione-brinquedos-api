<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateCouponRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        $couponId = $this->route('coupon')?->id ?? $this->route('coupon');

        return [
            'code' => [
                'sometimes',
                'required',
                'string',
                'max:50',
                Rule::unique('coupons', 'code')->ignore($couponId),
            ],
            'type' => ['sometimes', 'required', 'in:fixed,percentage'],
            'value' => ['sometimes', 'required', 'numeric', 'min:0.01'],
            'max_uses' => ['sometimes', 'nullable', 'integer', 'min:1'],
            'min_order_value' => ['sometimes', 'nullable', 'numeric', 'min:0'],
            'active' => ['sometimes', 'boolean'],
            'expires_at' => ['sometimes', 'nullable', 'date', 'after:now'],
        ];
    }

    public function messages(): array
    {
        return [
            'code.required' => 'O código do cupom não pode ficar vazio.',
            'code.unique' => 'Já existe um cupom com esse código.',
            'code.max' => 'O código deve ter no máximo 50 caracteres.',
            'type.required' => 'O tipo de desconto não pode ficar vazio.',
            'type.in' => 'O tipo de desconto deve ser "fixed" (valor fixo) ou "percentage" (porcentagem).',
            'value.required' => 'O valor do desconto não pode ficar vazio.',
            'value.numeric' => 'O valor do desconto deve ser numérico.',
            'value.min' => 'O valor do desconto deve ser maior que zero.',
            'max_uses.integer' => 'O limite de usos deve ser um número inteiro.',
            'max_uses.min' => 'O limite de usos deve ser no mínimo 1.',
            'min_order_value.numeric' => 'O valor mínimo do pedido deve ser numérico.',
            'min_order_value.min' => 'O valor mínimo do pedido não pode ser negativo.',
            'expires_at.date' => 'A data de expiração é inválida.',
            'expires_at.after' => 'A data de expiração deve ser futura.',
        ];
    }
}