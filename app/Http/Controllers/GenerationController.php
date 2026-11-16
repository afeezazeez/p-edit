<?php

namespace App\Http\Controllers;

use App\Http\Requests\EstimateGenerationRequest;
use App\Http\Requests\StoreGenerationRequest;
use App\Http\Resources\GenerationEstimateResource;
use App\Http\Resources\GenerationResource;
use App\Pixelforge\GenerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class GenerationController extends Controller
{
    public function index(Request $request, GenerationService $service): JsonResponse
    {
        return GenerationResource::collection($service->history($request->user()))
            ->response()
            ->setStatusCode(HttpResponse::HTTP_OK);
    }

    public function estimate(EstimateGenerationRequest $request, GenerationService $service): JsonResponse
    {
        return GenerationEstimateResource::make($service->estimate($request->validated()))
            ->response()
            ->setStatusCode(HttpResponse::HTTP_OK);
    }

    public function store(StoreGenerationRequest $request, GenerationService $service): JsonResponse
    {
        $generation = $service->create(
            $request->user(), $request->validated(), $request->idempotencyKey(),
        );

        return GenerationResource::make($generation)
            ->response()
            ->setStatusCode(HttpResponse::HTTP_ACCEPTED);
    }
}
