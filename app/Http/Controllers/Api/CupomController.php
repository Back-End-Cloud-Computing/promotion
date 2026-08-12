<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCupomRequest;
use App\Http\Requests\UpdateCupomRequest;
use App\Http\Resources\CupomResource;
use App\Models\Cupom;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CupomController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return CupomResource::collection(Cupom::all());
    }

    public function store(StoreCupomRequest $request): JsonResponse
    {
        $cupom = Cupom::create($request->validated());

        return CupomResource::make($cupom)
            ->response()
            ->setStatusCode(201);
    }

    public function show(Cupom $cupom): CupomResource
    {
        return CupomResource::make($cupom);
    }

    public function update(UpdateCupomRequest $request, Cupom $cupom): CupomResource
    {
        $cupom->update($request->validated());

        return CupomResource::make($cupom);
    }

    public function destroy(Cupom $cupom): Response
    {
        $cupom->delete();

        return response()->noContent();
    }
}
