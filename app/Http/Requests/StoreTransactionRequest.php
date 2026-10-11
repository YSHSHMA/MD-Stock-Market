<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

final class StoreTransactionRequest extends FormRequest
{
  public function authorize(): bool
  {
    return true;
  }

  /**
   * @return array<string, mixed>
   */
  public function rules(): array
  {
    return [
      'symbol' => 'required|string',
      'type' => 'required|in:buy,sell',
      'quantity' => 'required|numeric|min:0.00000001',
      'price' => 'required|numeric|min:0',
      'date' => 'nullable|date',
    ];
  }
}
