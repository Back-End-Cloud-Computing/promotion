<?php

namespace App\Domain\Promotions\Entities;

use App\Domain\Campaigns\Entities\Campaign;
use App\Domain\Promotions\Enums\ProductCategory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $product_id
 * @property int|null $campaign_id
 * @property int $discount_percentage
 * @property ProductCategory $category
 * @property bool $active
 * @property-read Campaign|null $campaign
 */
class Promotion extends Model
{
    use HasFactory;

    protected $table = 'promotions';

    protected $fillable = ['product_id', 'campaign_id', 'discount_percentage', 'category', 'active'];

    // O default do banco não hidrata o model em memória após um INSERT — sem
    // isto, o JSON de resposta do POST mostra `active: null` até a próxima leitura.
    protected $attributes = ['active' => true];

    protected function casts(): array
    {
        return [
            'product_id' => 'integer',
            'discount_percentage' => 'integer',
            'category' => ProductCategory::class,
            'active' => 'boolean',
        ];
    }

    public function campaign(): BelongsTo
    {
        return $this->belongsTo(Campaign::class);
    }

    /**
     * Promoção sem campanha vale enquanto estiver ativa; com campanha, depende
     * da vigência dela.
     *
     * @param  Builder<Promotion>  $query
     * @return Builder<Promotion>
     */
    public function scopeValid(Builder $query): Builder
    {
        return $query->where('active', true)
            ->where(function (Builder $q) {
                $q->whereNull('campaign_id')
                    ->orWhereHas('campaign', fn (Builder $c) => Campaign::applyValidityScope($c));
            });
    }

    public function isValid(): bool
    {
        return $this->active && ($this->campaign === null || $this->campaign->isValid());
    }
}
