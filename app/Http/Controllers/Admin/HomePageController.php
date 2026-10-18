<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Brand;
use App\Models\Category;
use App\Support\HomeLayout;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Admin → Home page: move, hide and remove the home page's blocks, add product rows and text
 * blocks, preview the draft, then publish it. Admins only.
 */
class HomePageController extends Controller
{
    public function edit()
    {
        return view('admin.home-page.edit', [
            'blocks' => HomeLayout::draft(),
            'hasDraft' => HomeLayout::hasDraft(),
            'categories' => Category::orderBy('name')->get(['id', 'name']),
            'brands' => Brand::orderBy('name')->get(['id', 'name']),
        ]);
    }

    /**
     * Move a block up or down, hide or show it, or remove one staff added.
     */
    public function move(Request $request, string $id)
    {
        $data = $request->validate(['action' => 'required|in:up,down,toggle,remove']);
        $blocks = HomeLayout::draft();
        $index = collect($blocks)->search(fn ($b) => ($b['id'] ?? null) === $id);
        abort_if($index === false, 404);

        $swap = ['up' => $index - 1, 'down' => $index + 1][$data['action']] ?? null;
        if ($swap !== null && isset($blocks[$swap])) {
            [$blocks[$swap], $blocks[$index]] = [$blocks[$index], $blocks[$swap]];
        } elseif ($data['action'] === 'toggle') {
            $blocks[$index]['visible'] = ! ($blocks[$index]['visible'] ?? true);
        } elseif ($data['action'] === 'remove' && isset(HomeLayout::CUSTOM[$blocks[$index]['type']])) {
            array_splice($blocks, $index, 1); // built-in blocks can only be hidden
        }
        HomeLayout::saveDraft($blocks);

        return redirect()->to(route('admin.home-page').'#block-'.$id);
    }

    public function store(Request $request)
    {
        $blocks = HomeLayout::draft();
        $blocks[] = ['id' => HomeLayout::newId(), 'visible' => true] + $this->validated($request);
        HomeLayout::saveDraft($blocks);

        return back()->with('success', 'Block added to the draft.');
    }

    public function update(Request $request, string $id)
    {
        $blocks = HomeLayout::draft();
        $index = collect($blocks)->search(fn ($b) => ($b['id'] ?? null) === $id);
        abort_if($index === false || ! isset(HomeLayout::CUSTOM[$blocks[$index]['type']]), 404);
        $blocks[$index] = ['id' => $id, 'visible' => $blocks[$index]['visible'] ?? true] + $this->validated($request);
        HomeLayout::saveDraft($blocks);

        return back()->with('success', 'Block saved in the draft.');
    }

    public function publish(Request $request)
    {
        $request->validate(['action' => 'required|in:publish,discard']);
        if ($request->input('action') === 'publish') {
            HomeLayout::publish();
            \App\Support\Audit::record('home_page.published', null, ['blocks' => count(HomeLayout::published())]);

            return back()->with('success', 'Published. The home page now shows this layout.');
        }
        HomeLayout::discard();

        return back()->with('success', 'Draft discarded.');
    }

    /**
     * @return array<string, mixed>
     */
    protected function validated(Request $request): array
    {
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys(HomeLayout::CUSTOM))],
            'title_en' => 'nullable|string|max:120',
            'title_dv' => 'nullable|string|max:120',
            'source' => 'nullable|in:category,brand,products',
            'source_value' => 'nullable|integer',
            'products' => ['nullable', 'string', 'max:300', 'regex:/^[0-9,\s]*$/'],
            'text_en' => 'nullable|string|max:1000',
            'text_dv' => 'nullable|string|max:1000',
            'button_en' => 'nullable|string|max:40',
            'button_dv' => 'nullable|string|max:40',
            'link' => ['nullable', 'url:http,https', 'max:255'],
        ]);

        return array_map(fn ($v) => is_string($v) ? trim($v) : $v, array_filter($data, fn ($v) => $v !== null));
    }
}
