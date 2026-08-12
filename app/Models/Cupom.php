<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $codigo
 * @property string $tipo
 * @property string $valor
 * @property string $valor_minimo
 * @property int|null $limite_uso
 * @property int $usos
 * @property int|null $campanha_id
 * @property bool $ativo
 * @property-read Campanha|null $campanha
 */
class Cupom extends Model
{
    use HasFactory;

    protected $table = 'cupons';

    protected $fillable = [
        'codigo', 'tipo', 'valor', 'valor_minimo', 'limite_uso', 'campanha_id', 'ativo',
    ];

    protected function casts(): array
    {
        return [
            'valor' => 'decimal:2',
            'valor_minimo' => 'decimal:2',
            'limite_uso' => 'integer',
            'usos' => 'integer',
            'ativo' => 'boolean',
        ];
    }

    /**
     * Normaliza o código na aplicação, não na collation do banco: MySQL 8 é
     * case-insensitive por padrão e SQLite não é, então confiar no banco faria
     * a unicidade valer num ambiente e não no outro.
     */
    protected function codigo(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => mb_strtoupper(trim($value)),
        );
    }

    public function campanha(): BelongsTo
    {
        return $this->belongsTo(Campanha::class);
    }

    /**
     * @param  Builder<Cupom>  $query
     * @return Builder<Cupom>
     */
    public function scopePorCodigo(Builder $query, string $codigo): Builder
    {
        return $query->where('codigo', mb_strtoupper(trim($codigo)));
    }

    public function atingiuLimite(): bool
    {
        return $this->limite_uso !== null && $this->usos >= $this->limite_uso;
    }
}
