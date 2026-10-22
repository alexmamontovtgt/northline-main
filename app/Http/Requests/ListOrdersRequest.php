<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\OrderStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class ListOrdersRequest extends FormRequest
{
    /** @return array<string, array<int, mixed>> */
    public function rules(): array
    {
        return [
            'status' => ['nullable', Rule::in(array_column(OrderStatus::cases(), 'value'))],
            'email' => ['nullable', 'email', 'max:255'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        $statuses = implode(', ', array_map(fn (OrderStatus $status) => $status->value, OrderStatus::cases()));

        return [
            'status.in' => 'The status must be one of: '.$statuses.'.',
            'email.email' => 'Enter a full email address, for example buyer@company.test.',
            'email.max' => 'The email may not be longer than :max characters.',
            'page.integer' => 'The page must be a whole number.',
            'page.min' => 'The page must be at least :min.',
            'per_page.integer' => 'The page size must be a whole number.',
            'per_page.min' => 'The page size must be at least :min.',
            'per_page.max' => 'The page size may not be greater than :max.',
        ];
    }

    public function status(): ?OrderStatus
    {
        return $this->enum('status', OrderStatus::class);
    }

    public function customerEmail(): ?string
    {
        return $this->filled('email') ? mb_strtolower($this->string('email')->trim()->toString()) : null;
    }

    public function perPage(): int
    {
        return $this->integer('per_page', 15);
    }
}
