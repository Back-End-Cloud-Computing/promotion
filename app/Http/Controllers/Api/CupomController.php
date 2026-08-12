<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCupomRequest;
use App\Http\Requests\UpdateCupomRequest;
use App\Http\Resources\CupomResource;
use App\Models\Cupom;
use Illuminate\Database\QueryException;
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
        // Unicidade não entra como regra de validação: o contrato promete 409
        // (conflito), não 422 (formato inválido), e o teste de R12 precisa
        // exercitar a constraint UNIQUE do banco, não uma checagem da aplicação
        // que corre antes dela.
        try {
            $cupom = Cupom::create($request->validated());
        } catch (QueryException $e) {
            if ((string) $e->getCode() !== '23000') {
                throw $e;
            }

            return response()->json(['error' => 'Código de cupom já existe'], 409);
        }

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
