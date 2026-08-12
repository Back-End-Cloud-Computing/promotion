<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCampanhaRequest;
use App\Http\Requests\UpdateCampanhaRequest;
use App\Http\Resources\CampanhaResource;
use App\Models\Campanha;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CampanhaController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return CampanhaResource::collection(Campanha::all());
    }

    public function store(StoreCampanhaRequest $request): JsonResponse
    {
        $campanha = Campanha::create($request->validated());

        return CampanhaResource::make($campanha)
            ->response()
            ->setStatusCode(201);
    }

    public function show(Campanha $campanha): CampanhaResource
    {
        return CampanhaResource::make($campanha);
    }

    public function update(UpdateCampanhaRequest $request, Campanha $campanha): CampanhaResource
    {
        $campanha->update($request->validated());

        return CampanhaResource::make($campanha);
    }

    public function destroy(Campanha $campanha): Response
    {
        $campanha->delete();

        return response()->noContent();
    }
}
