<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Island;
use App\Services\IslandDirectory;
use App\Services\ShoppingIsland;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * "Delivering to <island>": the island the customer shops for and how listings are shown for it
 * (App\Services\ShoppingIsland). Every action answers JSON when asked (the picker's script, the
 * phone app), else goes back to the page it came from.
 */
class DeliveryIslandController extends Controller
{
    public function __construct(protected ShoppingIsland $shopping, protected IslandDirectory $islands) {}

    /** The current choice and the islands to choose from. */
    public function show(): JsonResponse
    {
        return response()->json($this->shopping->state() + ['atolls' => $this->islands->grouped()]);
    }

    public function store(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'island_id' => ['required', 'integer', Rule::exists(Island::class, 'id')->where('is_active', true)],
            'view' => ['nullable', Rule::in(ShoppingIsland::VIEWS)],
        ]);

        $island = $this->islands->find((int) $data['island_id']) ?? Island::findOrFail((int) $data['island_id']);
        $this->shopping->choose($island, $data['view'] ?? null);

        return $this->done($request);
    }

    /** "Use my location": the nearest island to the browser's position. */
    public function locate(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate([
            'lat' => ['required', 'numeric', 'between:-90,90'],
            'lng' => ['required', 'numeric', 'between:-180,180'],
        ]);

        $nearest = $this->islands->nearest((float) $data['lat'], (float) $data['lng']);
        if (! $nearest) {
            throw ValidationException::withMessages(['lat' => __('We could not find an island near you. Please choose your island from the list.')]);
        }
        $this->shopping->choose($nearest['island']);

        return $this->done($request, ['distance_km' => round($nearest['km'], 1)]);
    }

    /** Delivers to <island> / Only shops on <island> / Show everything. */
    public function view(Request $request): JsonResponse|RedirectResponse
    {
        $data = $request->validate(['view' => ['required', Rule::in(ShoppingIsland::VIEWS)]]);
        $this->shopping->setView($data['view']);

        return $this->done($request);
    }

    /** "Where should we deliver?" closed without choosing: see everything, don't ask again. */
    public function dismiss(Request $request): JsonResponse|RedirectResponse
    {
        $this->shopping->dismissPrompt();

        return $this->done($request);
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    protected function done(Request $request, array $extra = []): JsonResponse|RedirectResponse
    {
        request()->attributes->remove('shopping_island');
        if ($request->expectsJson()) {
            return response()->json($this->shopping->state() + $extra);
        }

        // Back to the page it came from (only ever a page of this site); a product page at its delivery box
        $root = url('/');
        $previous = url()->previous();
        $back = $previous === $root || str_starts_with($previous, $root.'/') ? $previous : $root;
        $back = strtok($back, '#') ?: $root;
        $anchor = preg_match('#/products/[^/?]+#', (string) parse_url($back, PHP_URL_PATH)) ? '#delivery-box' : '';

        return redirect()->to($back.$anchor);
    }
}
