<?php

namespace App\Http\Resources;

use App\Models\Cupom;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Cupom */
class CupomResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'codigo' => $this->codigo,
            'tipo' => $this->tipo,
            'valor' => $this->valor,
            'valor_minimo' => $this->valor_minimo,
            'limite_uso' => $this->limite_uso,
            'usos' => $this->usos,
            'campanha_id' => $this->campanha_id,
            'ativo' => $this->ativo,
            'created_at' => $this->created_at,
        ];
    }
}
