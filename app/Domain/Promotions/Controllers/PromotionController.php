<?php

namespace App\Domain\Promotions\Controllers;

use App\Domain\Promotions\Entities\Promotion;
use App\Domain\Promotions\Requests\StorePromotionRequest;
use App\Domain\Promotions\Requests\UpdatePromotionRequest;
use App\Domain\Promotions\Resources\PromotionResource;
use App\Http\Controllers\Controller;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class PromotionController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $promotions = Promotion::query()
            ->when($request->filled('category'), fn ($q) => $q->where('category', $request->string('category')))
            ->when($request->has('active'), fn ($q) => $q->where('active', $request->boolean('active')))
            ->get();

        return PromotionResource::collection($promotions);
    }

    public function store(StorePromotionRequest $request): JsonResponse
    {
        // Mesmo motivo do CouponController: 409 é o contrato, e o teste precisa
        // provar a constraint UNIQUE do banco, não uma validação da aplicação.
        try {
            $promotion = Promotion::create($request->validated());
        } catch (QueryException $e) {
            if ((string) $e->getCode() !== '23000') {
                throw $e;
            }

            return response()->error(409, 'Produto já possui promoção');
        }

        return PromotionResource::make($promotion)
            ->response()
            ->setStatusCode(201);
    }

    public function show(Promotion $promotion): PromotionResource
    {
        return PromotionResource::make($promotion);
    }

    public function update(UpdatePromotionRequest $request, Promotion $promotion): PromotionResource
    {
        $promotion->update($request->validated());

        return PromotionResource::make($promotion);
    }

    public function destroy(Promotion $promotion): Response
    {
        $promotion->delete();

        return response()->noContent();
    }
}
