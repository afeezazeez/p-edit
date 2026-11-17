<?php

namespace App\Http\Controllers;

use App\Http\Requests\EstimateGenerationRequest;
use App\Http\Requests\GenerationHistoryRequest;
use App\Http\Requests\StoreGenerationRequest;
use App\Http\Resources\GenerationEstimateResource;
use App\Http\Resources\GenerationResource;
use App\Services\GenerationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class GenerationController extends Controller
{
    public function create(): InertiaResponse
    {
        return Inertia::render('Generations/Create');
    }

    public function index(GenerationHistoryRequest $request, GenerationService $service): InertiaResponse|JsonResponse
    {
        $status = $request->validated('status', 'all');
        $generations = $service->history($request->user(), $status);
        if ($request->expectsJson()) {
            return GenerationResource::collection($generations)
                ->response()
                ->setStatusCode(HttpResponse::HTTP_OK);
        }

        $history = GenerationResource::collection($generations)
            ->response()
            ->getData(true);

        return Inertia::render('Generations/Index', [
            'generations' => $history,
            'filters' => ['status' => $status],
        ]);
    }

    public function estimate(EstimateGenerationRequest $request, GenerationService $service): JsonResponse
    {
        return GenerationEstimateResource::make($service->estimate($request->validated()))
            ->response()
            ->setStatusCode(HttpResponse::HTTP_OK);
    }

    public function store(StoreGenerationRequest $request, GenerationService $service): JsonResponse|RedirectResponse
    {
        $generation = $service->create(
            $request->user(), $request->validated(), $request->idempotencyKey(),
        );

        if ($request->expectsJson()) {
            return GenerationResource::make($generation)
                ->response()
                ->setStatusCode(HttpResponse::HTTP_ACCEPTED);
        }

        Inertia::flash('toast', [
            'type' => 'success',
            'message' => 'Your image request was submitted.',
        ]);

        return to_route('generations.index');
    }
}
