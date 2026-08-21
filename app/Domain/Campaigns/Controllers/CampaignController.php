<?php

namespace App\Domain\Campaigns\Controllers;

use App\Domain\Campaigns\Entities\Campaign;
use App\Domain\Campaigns\Requests\StoreCampaignRequest;
use App\Domain\Campaigns\Requests\UpdateCampaignRequest;
use App\Domain\Campaigns\Resources\CampaignResource;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class CampaignController extends Controller
{
    public function index(): AnonymousResourceCollection
    {
        return CampaignResource::collection(Campaign::all());
    }

    public function store(StoreCampaignRequest $request): JsonResponse
    {
        $campaign = Campaign::create($request->validated());

        return CampaignResource::make($campaign)
            ->response()
            ->setStatusCode(201);
    }

    public function show(Campaign $campaign): CampaignResource
    {
        return CampaignResource::make($campaign);
    }

    public function update(UpdateCampaignRequest $request, Campaign $campaign): CampaignResource
    {
        $campaign->update($request->validated());

        return CampaignResource::make($campaign);
    }

    public function destroy(Campaign $campaign): Response
    {
        $campaign->delete();

        return response()->noContent();
    }
}
