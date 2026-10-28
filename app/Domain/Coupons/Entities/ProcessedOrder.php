<?php

namespace App\Domain\Coupons\Entities;

use Illuminate\Database\Eloquent\Model;

/**
 * @property string $order_id
 * @property string|null $coupon_code
 * @property string $outcome
 */
class ProcessedOrder extends Model
{
    protected $primaryKey = 'order_id';

    public $incrementing = false;

    protected $keyType = 'string';

    protected $fillable = ['order_id', 'coupon_code', 'outcome'];
}
