<?php

namespace App\Http\Resources;

use App\Models\Promocao;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Promocao */
class PromocaoResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'produto_id' => $this->produto_id,
            'campanha_id' => $this->campanha_id,
            'desconto_pct' => $this->desconto_pct,
            'categoria' => $this->categoria,
            'ativo' => $this->ativo,
            'created_at' => $this->created_at,
        ];
    }
}
