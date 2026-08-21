<?php

namespace App\Domain\Campaigns\Entities;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $name
 * @property string|null $description
 * @property Carbon $starts_at
 * @property Carbon $ends_at
 * @property bool $active
 */
class Campaign extends Model
{
    use HasFactory;

    protected $table = 'campaigns';

    protected $fillable = ['name', 'description', 'starts_at', 'ends_at', 'active'];

    // O default do banco não hidrata o model em memória após um INSERT — sem
    // isto, o JSON de resposta do POST mostra `active: null` até a próxima leitura.
    protected $attributes = ['active' => true];

    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'active' => 'boolean',
        ];
    }

    /**
     * A condição de vigência num lugar só. Existe como método estático porque
     * dentro de whereHas() o Builder chega sem o tipo do model, e duplicar a
     * comparação de data seria duplicar o lugar onde ela pode ficar errada.
     *
     * @param  Builder<covariant \Illuminate\Database\Eloquent\Model>  $query
     */
    public static function applyValidityScope(Builder $query): void
    {
        $query->where('active', true)
            ->where('starts_at', '<=', now())
            ->where('ends_at', '>=', now());
    }

    /**
     * @param  Builder<Campaign>  $query
     * @return Builder<Campaign>
     */
    public function scopeValid(Builder $query): Builder
    {
        static::applyValidityScope($query);

        return $query;
    }

    public function isValid(): bool
    {
        return $this->active
            && $this->starts_at->lessThanOrEqualTo(now())
            && $this->ends_at->greaterThanOrEqualTo(now());
    }
}
