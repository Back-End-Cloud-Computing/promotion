<?php

namespace App\Domain\Coupons\Controllers;

use App\Domain\Coupons\Entities\Coupon;
use App\Domain\Coupons\Requests\StoreCouponRequest;
use App\Domain\Coupons\Requests\UpdateCouponRequest;
use App\Domain\Coupons\Resources\CouponResource;
use App\Http\Controllers\Controller;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CouponController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return CouponResource::collection(Coupon::all());
    }

    public function store(StoreCouponRequest $request): JsonResponse
    {
        // Unicidade não entra como regra de validação: o contrato promete 409
        // (conflito), não 422 (formato inválido), e o teste de R12 precisa
        // exercitar a constraint UNIQUE do banco, não uma checagem da aplicação
        // que corre antes dela.
        try {
            $coupon = Coupon::create($request->validated());
        } catch (QueryException $e) {
            if ((string) $e->getCode() !== '23000') {
                throw $e;
            }

            return response()->json(['error' => 'Código de cupom já existe'], 409);
        }

        return CouponResource::make($coupon)
            ->response()
            ->setStatusCode(201);
    }

    public function show(Coupon $coupon): CouponResource
    {
        return CouponResource::make($coupon);
    }

    public function update(UpdateCouponRequest $request, Coupon $coupon): CouponResource
    {
        $coupon->update($request->validated());

        return CouponResource::make($coupon);
    }

    public function destroy(Coupon $coupon): Response
    {
        $coupon->delete();

        return response()->noContent();
    }
}
