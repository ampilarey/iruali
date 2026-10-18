@php $field = 'mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2 text-sm'; @endphp
<form method="POST" action="{{ $action }}" class="mt-2 grid grid-cols-1 gap-3 sm:grid-cols-2">
    @csrf @if($method === 'PUT') @method('PUT') @endif
    <div><label class="block text-xs font-medium text-gray-700">Type
        <select name="type" class="{{ $field }}">@foreach(\App\Support\HomeLayout::CUSTOM as $key => $label)<option value="{{ $key }}" @selected(($values['type'] ?? '') === $key)>{{ $label }}</option>@endforeach</select></label></div>
    <div><label class="block text-xs font-medium text-gray-700">Link (optional: "See all" or the button)
        <input name="link" type="url" value="{{ $values['link'] ?? '' }}" class="{{ $field }}" placeholder="https://iruali.mv/deals"></label>
        @error('link')<p class="text-xs text-red-700">{{ $message }}</p>@enderror</div>
    <div><label class="block text-xs font-medium text-gray-700">Title (English)<input name="title_en" maxlength="120" value="{{ $values['title_en'] ?? '' }}" class="{{ $field }}"></label></div>
    <div><label class="block text-xs font-medium text-gray-700">Title (Dhivehi)<input name="title_dv" maxlength="120" dir="rtl" value="{{ $values['title_dv'] ?? '' }}" class="{{ $field }}"></label></div>
    <fieldset class="sm:col-span-2 rounded border border-gray-200 p-3">
        <legend class="px-1 text-xs font-medium text-gray-700">Product row: which products</legend>
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <label class="block text-xs">From
                <select name="source" class="{{ $field }}">
                    @foreach(['category' => 'A category', 'brand' => 'A brand', 'products' => 'Picked products'] as $key => $label)<option value="{{ $key }}" @selected(($values['source'] ?? '') === $key)>{{ $label }}</option>@endforeach
                </select></label>
            <label class="block text-xs">Category or brand
                <select name="source_value" class="{{ $field }}">
                    <option value="">—</option>
                    <optgroup label="Categories">@foreach($categories ?? [] as $category)<option value="{{ $category->id }}" @selected(($values['source'] ?? '') === 'category' && (int) ($values['source_value'] ?? 0) === $category->id)>{{ $category->getTranslation('name', 'en') }}</option>@endforeach</optgroup>
                    <optgroup label="Brands">@foreach($brands ?? [] as $brand)<option value="{{ $brand->id }}" @selected(($values['source'] ?? '') === 'brand' && (int) ($values['source_value'] ?? 0) === $brand->id)>{{ $brand->name }}</option>@endforeach</optgroup>
                </select></label>
            <label class="block text-xs">Product ids, in order
                <input name="products" value="{{ $values['products'] ?? '' }}" placeholder="12, 40, 7" class="{{ $field }}"></label>
        </div>
    </fieldset>
    <fieldset class="sm:col-span-2 rounded border border-gray-200 p-3 grid grid-cols-1 gap-3 sm:grid-cols-2">
        <legend class="px-1 text-xs font-medium text-gray-700">Text block</legend>
        <label class="block text-xs">Text (English)<textarea name="text_en" rows="2" maxlength="1000" class="{{ $field }}">{{ $values['text_en'] ?? '' }}</textarea></label>
        <label class="block text-xs">Text (Dhivehi)<textarea name="text_dv" rows="2" maxlength="1000" dir="rtl" class="{{ $field }}">{{ $values['text_dv'] ?? '' }}</textarea></label>
        <label class="block text-xs">Button (English)<input name="button_en" maxlength="40" value="{{ $values['button_en'] ?? '' }}" class="{{ $field }}"></label>
        <label class="block text-xs">Button (Dhivehi)<input name="button_dv" maxlength="40" dir="rtl" value="{{ $values['button_dv'] ?? '' }}" class="{{ $field }}"></label>
    </fieldset>
    <div class="sm:col-span-2"><button class="rounded-lg bg-primary-600 px-4 py-2 font-semibold text-white hover:bg-primary-700">{{ $method === 'PUT' ? 'Save block' : 'Add to draft' }}</button></div>
</form>
