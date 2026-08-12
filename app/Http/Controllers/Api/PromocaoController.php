<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorePromocaoRequest;
use App\Http\Requests\UpdatePromocaoRequest;
use App\Http\Resources\PromocaoResource;
use App\Models\Promocao;
use Illuminate\Database\QueryException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class PromocaoController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $promocoes = Promocao::query()
            ->when($request->filled('categoria'), fn ($q) => $q->where('categoria', $request->string('categoria')))
            ->when($request->has('ativo'), fn ($q) => $q->where('ativo', $request->boolean('ativo')))
            ->get();

        return PromocaoResource::collection($promocoes);
    }

    public function store(StorePromocaoRequest $request): JsonResponse
    {
        // Mesmo motivo do CupomController: 409 é o contrato, e o teste precisa
        // provar a constraint UNIQUE do banco, não uma validação da aplicação.
        try {
            $promocao = Promocao::create($request->validated());
        } catch (QueryException $e) {
            if ((string) $e->getCode() !== '23000') {
                throw $e;
            }

            return response()->json(['error' => 'Produto já possui promoção'], 409);
        }

        return PromocaoResource::make($promocao)
            ->response()
            ->setStatusCode(201);
    }

    public function show(Promocao $promocao): PromocaoResource
    {
        return PromocaoResource::make($promocao);
    }

    public function update(UpdatePromocaoRequest $request, Promocao $promocao): PromocaoResource
    {
        $promocao->update($request->validated());

        return PromocaoResource::make($promocao);
    }

    public function destroy(Promocao $promocao): Response
    {
        $promocao->delete();

        return response()->noContent();
    }
}
