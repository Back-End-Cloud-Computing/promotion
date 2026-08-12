<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $produto_id
 * @property int|null $campanha_id
 * @property int $desconto_pct
 * @property string $categoria
 * @property bool $ativo
 * @property-read Campanha|null $campanha
 */
class Promocao extends Model
{
    use HasFactory;

    protected $table = 'promocoes';

    protected $fillable = ['produto_id', 'campanha_id', 'desconto_pct', 'categoria', 'ativo'];

    // O default do banco não hidrata o model em memória após um INSERT — sem
    // isto, o JSON de resposta do POST mostra `ativo: null` até a próxima leitura.
    protected $attributes = ['ativo' => true];

    protected function casts(): array
    {
        return [
            'produto_id' => 'integer',
            'desconto_pct' => 'integer',
            'ativo' => 'boolean',
        ];
    }

    public function campanha(): BelongsTo
    {
        return $this->belongsTo(Campanha::class);
    }

    /**
     * Promoção sem campanha vale enquanto estiver ativa; com campanha, depende
     * da vigência dela.
     *
     * @param  Builder<Promocao>  $query
     * @return Builder<Promocao>
     */
    public function scopeVigente(Builder $query): Builder
    {
        return $query->where('ativo', true)
            ->where(function (Builder $q) {
                $q->whereNull('campanha_id')
                    ->orWhereHas('campanha', fn (Builder $c) => Campanha::aplicarVigencia($c));
            });
    }

    public function estaVigente(): bool
    {
        return $this->ativo && ($this->campanha === null || $this->campanha->estaVigente());
    }
}
