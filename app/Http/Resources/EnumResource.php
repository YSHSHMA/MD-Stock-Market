<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Traits\Enums\EnumHelper;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin EnumHelper
 */
class EnumResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'value' => $this->value,
            'label' => $this->label(),
        ];
    }
}