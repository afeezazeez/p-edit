<?php

namespace App\Http\Controllers;

use App\Pixelforge\PixelforgeWebhookService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\Response as HttpResponse;

class PixelforgeWebhookController extends Controller
{
    public function __invoke(Request $request, PixelforgeWebhookService $webhooks): Response
    {
        $webhooks->receive(
            $request->getContent(),
            (string) $request->header('Pixelforge-Signature'),
            $request->header('Pixelforge-Event-Id'),
        );

        return response()->noContent(HttpResponse::HTTP_NO_CONTENT);
    }
}
