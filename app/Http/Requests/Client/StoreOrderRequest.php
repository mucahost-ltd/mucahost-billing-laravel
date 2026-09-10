<?php

namespace App\Http\Requests\Client;

use App\Models\ProductPricing;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreOrderRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('client')?->status === 'active';
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'pricing_id' => ['required', 'integer', 'exists:product_pricing,id'],
            'checkout_token' => ['required', 'uuid'],
            'domain' => [Rule::requiredIf((bool) ProductPricing::query()->find($this->integer('pricing_id'))?->product?->requires_domain), 'nullable', 'string', 'max:253', 'regex:/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\.)+[a-z]{2,63}$/i'],
        ];
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->domain)) {
            $this->merge(['domain' => strtolower(trim($this->domain))]);
        }
    }
}
