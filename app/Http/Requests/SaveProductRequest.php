<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class SaveProductRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user('web')?->role === 'admin';
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'product_group_id' => ['required', 'integer', 'exists:product_groups,id'],
            'name' => ['required', 'string', 'max:255'],
            'slug' => ['required', 'string', 'max:255', 'alpha_dash:ascii', Rule::unique('products')->ignore($this->route('product'))],
            'description' => ['nullable', 'string', 'max:10000'],
            'type' => ['required', Rule::in(['shared_hosting', 'reseller_hosting', 'vps', 'custom'])],
            'billing_type' => ['required', Rule::in(['free', 'one_time', 'recurring'])],
            'module' => ['required', Rule::in(['manual', 'enhance'])],
            'setup_mode' => ['required', Rule::in(['on_order', 'after_payment', 'on_accept', 'manual'])],
            'plan_id' => ['nullable', Rule::requiredIf($this->input('module') === 'enhance'), 'integer', 'min:1'],
            'app_server_id' => ['nullable', 'uuid'],
            'is_active' => ['required', 'boolean'], 'is_visible' => ['required', 'boolean'],
            'requires_domain' => ['required', 'boolean'],
            'sort_order' => ['required', 'integer', 'min:0', 'max:99999'],
            'pricing' => ['required', 'array', 'min:1', 'max:100'],
            'pricing.*' => ['required', 'array:currency_id,billing_cycle,price,setup_fee'],
            'pricing.*.currency_id' => ['required', 'integer', 'exists:currencies,id'],
            'pricing.*.billing_cycle' => ['required', Rule::in(['monthly', 'quarterly', 'semiannually', 'annually', 'biennially', 'triennially', 'one_time'])],
            'pricing.*.price' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:49999999.99'],
            'pricing.*.setup_fee' => ['required', 'numeric', 'decimal:0,2', 'min:0', 'max:49999999.99'],
        ];
    }

    /** @return list<\Closure> */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if ($validator->errors()->isNotEmpty()) {
                return;
            }
            if ($this->input('module') === 'enhance' && ($this->input('type') !== 'shared_hosting' || ! $this->boolean('requires_domain'))) {
                $validator->errors()->add('module', 'Enhance website provisioning requires shared hosting and a domain.');
            }
            $seen = [];
            foreach ($this->input('pricing') as $index => $row) {
                $key = $row['currency_id'].':'.$row['billing_cycle'];
                if (isset($seen[$key])) {
                    $validator->errors()->add("pricing.$index.billing_cycle", 'Use each currency and billing cycle only once.');
                }
                $seen[$key] = true;
                if (($this->input('billing_type') === 'recurring') === ($row['billing_cycle'] === 'one_time')) {
                    $validator->errors()->add("pricing.$index.billing_cycle", 'Choose a billing cycle that matches the payment type.');
                }
                if ($this->input('billing_type') === 'free' && ((float) $row['price'] !== 0.0 || (float) $row['setup_fee'] !== 0.0)) {
                    $validator->errors()->add("pricing.$index.price", 'Free products cannot have a price or setup fee.');
                }
            }
        }];
    }
}
