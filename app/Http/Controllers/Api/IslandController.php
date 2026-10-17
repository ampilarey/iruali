<?php

namespace App\Http\Controllers\Api;

use App\Models\Island;
use App\Models\User;
use App\Services\IslandDirectory;
use App\Services\ShopCoverage;
use App\Services\ShoppingIsland;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

/**
 * Islands for the phone app: the list (grouped by atoll), the nearest island to a position, and
 * the signed-in customer's "Delivering to" island and view (the same ones the website uses).
 * Product lists take island_id and view to show what reaches the island.
 */
class IslandController extends BaseController
{
    public function __construct(protected IslandDirectory $islands) {}

    public function index(): JsonResponse
    {
        return $this->sendResponse(['atolls' => $this->islands->grouped(), 'views' => ShoppingIsland::VIEWS]);
    }

    public function nearest(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);
        if ($validator->fails()) {
            return $this->sendValidationError($validator->errors());
        }

        $nearest = $this->islands->nearest((float) $request->input('lat'), (float) $request->input('lng'));
        if (! $nearest) {
            return $this->sendError(__('We could not find an island near you. Please choose your island from the list.'), [], 404);
        }

        return $this->sendResponse(['island' => $this->islands->toArray($nearest['island']), 'distance_km' => round($nearest['km'], 1)]);
    }

    public function show(Request $request): JsonResponse
    {
        return $this->sendResponse($this->state($request->user()));
    }

    public function update(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'island_id' => ['required', 'integer', Rule::exists(Island::class, 'id')->where('is_active', true)],
            'view' => ['nullable', Rule::in(ShoppingIsland::VIEWS)],
        ]);
        if ($validator->fails()) {
            return $this->sendValidationError($validator->errors());
        }

        /** @var User $user */
        $user = $request->user();
        $user->forceFill([
            'shopping_island_id' => (int) $request->input('island_id'),
            'shopping_view' => $request->input('view') ?: ($user->shopping_view ?: ShoppingIsland::VIEW_DELIVERS),
        ])->save();

        return $this->sendResponse($this->state($user->fresh()), 'Delivering to island saved');
    }

    /**
     * @return array<string, mixed>
     */
    protected function state(?User $user): array
    {
        $island = $user?->shopping_island_id ? Island::find($user->shopping_island_id) : null;

        return [
            'island' => $island ? $this->islands->toArray($island) : null,
            'view' => in_array($user?->shopping_view, ShoppingIsland::VIEWS, true) ? $user->shopping_view : ShoppingIsland::VIEW_DELIVERS,
            'views' => ShoppingIsland::VIEWS,
            'has_local_shops' => $island ? app(ShopCoverage::class)->hasLocalShops($island) : false,
        ];
    }
}
