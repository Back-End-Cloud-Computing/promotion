<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $nome
 * @property string|null $descricao
 * @property Carbon $inicia_em
 * @property Carbon $termina_em
 * @property bool $ativo
 */
class Campanha extends Model
{
    use HasFactory;

    protected $table = 'campanhas';

    protected $fillable = ['nome', 'descricao', 'inicia_em', 'termina_em', 'ativo'];

    // O default do banco não hidrata o model em memória após um INSERT — sem
    // isto, o JSON de resposta do POST mostra `ativo: null` até a próxima leitura.
    protected $attributes = ['ativo' => true];

    protected function casts(): array
    {
        return [
            'inicia_em' => 'datetime',
            'termina_em' => 'datetime',
            'ativo' => 'boolean',
        ];
    }

    /**
     * A condição de vigência num lugar só. Existe como método estático porque
     * dentro de whereHas() o Builder chega sem o tipo do model, e duplicar a
     * comparação de data seria duplicar o lugar onde ela pode ficar errada.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     */
    public static function aplicarVigencia(Builder $query): void
    {
        $query->where('ativo', true)
            ->where('inicia_em', '<=', now())
            ->where('termina_em', '>=', now());
    }

    /**
     * @param  Builder<Campanha>  $query
     * @return Builder<Campanha>
     */
    public function scopeVigente(Builder $query): Builder
    {
        static::aplicarVigencia($query);

        return $query;
    }

    public function estaVigente(): bool
    {
        return $this->ativo
            && $this->inicia_em->lessThanOrEqualTo(now())
            && $this->termina_em->greaterThanOrEqualTo(now());
    }
}
