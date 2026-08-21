<?php

namespace App\Domain\Coupons\Entities;

use App\Domain\Campaigns\Entities\Campaign;
use App\Domain\Coupons\Enums\CouponType;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $code
 * @property CouponType $type
 * @property string $value
 * @property string $minimum_value
 * @property int|null $usage_limit
 * @property int $usage_count
 * @property int|null $campaign_id
 * @property bool $active
 * @property-read Campaign|null $campaign
 */
class Coupon extends Model
{
    use HasFactory;

    protected $table = 'coupons';

    // `usage_count` é preenchível para as factories montarem cupons já
    // consumidos; as rotas admin passam por FormRequest, que não valida o
    // campo, então ele não entra por requisição.
    protected $fillable = [
        'code', 'type', 'value', 'minimum_value', 'usage_limit', 'usage_count', 'campaign_id', 'active',
    ];

    // O default do banco não hidrata o model em memória após um INSERT — sem
    // isto, o JSON de resposta do POST mostra `active: null`/`usage_count: null`
    // até a próxima leitura.
    protected $attributes = ['active' => true, 'usage_count' => 0, 'minimum_value' => 0];

    protected function casts(): array
    {
        return [
            'type' => CouponType::class,
            'value' => 'decimal:2',
            'minimum_value' => 'decimal:2',
            'usage_limit' => 'integer',
            'usage_count' => 'integer',
            'active' => 'boolean',
        ];
    }

    /**
     * Normaliza o código na aplicação, não na collation do banco: MySQL 8 é
     * case-insensitive por padrão e SQLite não é, então confiar no banco faria
     * a unicidade valer num ambiente e não no outro.
     */
    protected function code(): Attribute
    {
        return Attribute::make(
            set: fn (string $value) => mb_strtoupper(trim($value)),
        );
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * @param  Builder<Coupon>  $query
     * @return Builder<Coupon>
     */
    public function scopeByCode(Builder $query, string $code): Builder
    {
        return $query->where('code', mb_strtoupper(trim($code)));
    }

    public function hasReachedLimit(): bool
    {
        return $this->usage_limit !== null && $this->usage_count >= $this->usage_limit;
    }
}
