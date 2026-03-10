<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\NotificationTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TemplateController extends Controller
{
    /**
     * Return all templates grouped by event_type.
     */
    public function index(): JsonResponse
    {
        $templates = NotificationTemplate::all()
            ->groupBy('event_type')
            ->map(fn ($group) => $group->keyBy('language'));

        return response()->json([
            'success'   => true,
            'templates' => $templates,
        ]);
    }

    /**
     * Create or update a template for a given event type.
     */
    public function update(string $event, Request $request): JsonResponse
    {
        $validated = $request->validate([
            'language' => 'required|string|max:5',
            'template' => 'required|string|max:4096',
        ]);

        $template = NotificationTemplate::updateOrCreate(
            [
                'event_type' => $event,
                'language'   => $validated['language'],
            ],
            [
                'template'  => $validated['template'],
                'is_custom' => true,
            ],
        );

        return response()->json([
            'success'  => true,
            'template' => $template->toArray(),
        ]);
    }
}
