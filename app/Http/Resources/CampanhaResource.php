<?php

namespace App\Http\Resources;

use App\Models\Campanha;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Campanha */
class CampanhaResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'nome' => $this->nome,
            'descricao' => $this->descricao,
            'inicia_em' => $this->inicia_em,
            'termina_em' => $this->termina_em,
            'ativo' => $this->ativo,
            'created_at' => $this->created_at,
        ];
    }
}
