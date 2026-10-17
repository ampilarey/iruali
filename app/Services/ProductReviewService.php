<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\MultiBuyOffer;
use App\Models\Product;
use App\Models\ProductChangeRequest;
use App\Models\ProductVariant;
use App\Models\User;
use App\Notifications\ProductChangeReviewed;
use App\Support\Audit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Throwable;

/**
 * Every change a shop makes to an approved product waits for an admin, unless the shop is trusted
 * (User::autoApprovesListings(), turned on per shop by an admin).
 *
 * While an edit waits, the live product keeps the approved version: the edit is a
 * ProductChangeRequest holding the whole reviewed state the shop asked for (snapshot() format).
 * Stock is not reviewed and applies at once (product stock and low-stock alert, the existing
 * variants' stock and alert level), as does the shop hiding or showing its own approved product.
 * A product that was never approved is not public, so edits to it apply directly.
 *
 * One pending request per product: a new edit replaces it (Seller Centre form, bulk edit and CSV
 * import all build on the waiting one). Images uploaded with an edit are stored at once and listed
 * in the payload's "staged"; they go live on approval and are deleted on reject, replace or withdraw.
 * Approving applies the payload through ProductEditService, the code the product form uses.
 *
 * Payload: product (REVIEWED columns), stock_quantity (only used when the edit turns variants off),
 * variants (rows: id, attributes, sku, price, is_active, image, stock_quantity, low_stock_threshold),
 * multibuy (group, group_name, tiers), main_image, staged.
 */
class ProductReviewService
{
    /** The product columns a change to which needs review (the seller form's fields, plus sale_price from CSV). */
    public const REVIEWED = [
        'name', 'description', 'sku', 'category_id', 'price', 'compare_price', 'sale_price', 'flash_sale_ends_at',
        'has_variants', 'brand', 'weight', 'delivery_surcharge', 'video_provider', 'video_id',
        'preorder_enabled', 'preorder_ship_date', 'preorder_limit', 'preorder_note', 'preorder_closes_on', 'quote_min_quantity',
    ];

    /** The variant fields a change to which needs review (stock and its alert level do not). */
    public const REVIEWED_VARIANT = ['id', 'attributes', 'sku', 'price', 'is_active', 'image'];

    public function __construct(protected ProductEditService $editor, protected MultiBuyService $multibuy) {}

    // ---- Who is reviewed ---------------------------------------------------------------------

    /** The product's shop is trusted: its listings and edits go live without review. */
    public function trusts(Product $product, ?User $seller = null): bool
    {
        $seller ??= $product->seller;

        return $seller !== null && $seller->autoApprovesListings();
    }

    /** Changes to this product (other than stock and hide/show) wait for an admin. */
    public function needsReview(Product $product, ?User $seller = null): bool
    {
        return $product->isApproved() && ! $this->trusts($product, $seller);
    }

    /**
     * A trusted shop's new (never approved) listing goes live at once, approved automatically: the
     * audit log shows "product.auto_approved" with the shop as the actor. Not while the shop's
     * checklist would stop an admin approving it either (OnboardingService::blocksActivation()).
     */
    public function publishIfTrusted(Product $product, ?User $seller = null): bool
    {
        $seller ??= $product->seller;
        if ($product->isApproved() || ! $seller || ! $seller->autoApprovesListings()) {
            return false;
        }
        if (app(OnboardingService::class)->blocksActivation($seller)) {
            return false;
        }

        $product->forceFill(['is_active' => true, 'approved_at' => now()])->save();
        Audit::record('product.auto_approved', $product, ['name' => $product->getTranslation('name', 'en', false), 'shop' => $seller->shopName()]);

        return true;
    }

    // ---- The product form ----------------------------------------------------------------------

    /**
     * An edit from the Seller Centre product form to an approved product of a shop that is not
     * trusted: stock applies now, the rest becomes (or replaces) the pending request. Returns the
     * request, or null when nothing but stock changed (a waiting edit the form now matches the
     * live product again is withdrawn).
     *
     * @param  array<string, mixed>  $form  ProductEditService::formFromRequest()
     */
    public function submitForm(Product $product, array $form, ?User $by): ?ProductChangeRequest
    {
        $pending = $product->pendingChange;

        $this->applyFormStock($product, $form);
        $product->refresh();
        $live = $this->snapshot($product);

        // The waiting edit's files the form kept: the main image unless a new one was chosen, and
        // the variant images it sent back (variants[n][staged_image], only paths of that edit count)
        $stagedBefore = $pending ? $pending->stagedFiles() : [];
        $uploads = $form['main_image'] instanceof UploadedFile
            || collect($form['variants'])->contains(fn ($row) => empty($row['remove']) && ($row['image'] ?? null) instanceof UploadedFile);

        $payload = $this->payloadFromForm($product, $form, $pending, $live, $stagedBefore);

        if (! $uploads && ! $this->differs($live, $payload)) {
            if ($pending) {
                $this->withdraw($pending);
            }

            return null;
        }

        // Store the new uploads now; they go live only if the edit is approved
        $uploaded = [];
        if ($form['main_image'] instanceof UploadedFile && ($path = $this->editor->stageUpload($form['main_image'], 'products'))) {
            $payload['main_image'] = $path;
            $uploaded[] = $path;
        }
        foreach (array_values(array_filter($form['variants'], fn ($row) => empty($row['remove']))) as $i => $row) {
            if (($row['image'] ?? null) instanceof UploadedFile && isset($payload['variants'][$i]) && ($path = $this->editor->stageUpload($row['image'], 'products/variants'))) {
                $payload['variants'][$i]['image'] = $path;
                $uploaded[] = $path;
            }
        }
        $payload['staged'] = array_values(array_unique(array_merge(array_intersect($stagedBefore, $this->referencedFiles($payload)), $uploaded)));

        return $this->replacePending($product, $payload, $by, $pending);
    }

    /**
     * Stock fields from the form, applied at once: the product's stock (without variants) and
     * low-stock alert, and each existing variant's stock and alert level.
     *
     * @param  array<string, mixed>  $form
     */
    protected function applyFormStock(Product $product, array $form): void
    {
        // "Coming soon" and its launch day apply at once too (ComingSoonService)
        $attributes = Arr::only($form['product'], ['reorder_point']) + ($form['launch'] ?? []);
        if (! $product->has_variants && array_key_exists('stock_quantity', $form['product'])) {
            $attributes['stock_quantity'] = (int) $form['product']['stock_quantity'];
        }
        if ($attributes) {
            $product->forceFill($attributes)->save(); // coming_soon and available_on are not mass-assignable
        }

        $variants = $product->variants()->get()->keyBy('id');
        foreach ($form['variants'] as $row) {
            $variant = $variants->get((int) ($row['id'] ?? 0));
            if ($variant && empty($row['remove'])) {
                $variant->update([
                    'stock_quantity' => (int) $row['stock_quantity'],
                    'low_stock_threshold' => isset($row['low_stock_threshold']) && $row['low_stock_threshold'] !== '' ? (int) $row['low_stock_threshold'] : null,
                ]);
            }
        }
        $product->syncStockFromVariants();
    }

    /**
     * The reviewed state the form asks for, normalised like snapshot() so the two compare. Fields the
     * form did not send keep the waiting edit's value, else the live one.
     *
     * @param  array<string, mixed>  $form
     * @param  array<string, mixed>  $live
     * @param  list<string>  $stagedBefore
     * @return array<string, mixed>
     */
    protected function payloadFromForm(Product $product, array $form, ?ProductChangeRequest $pending, array $live, array $stagedBefore): array
    {
        $base = $pending ? $pending->payload : $live;

        // Fill a copy of the live product, so translations and casts come out as saving would make them
        $draft = clone $product;
        $draft->forceFill(Arr::except($form['product'], ['stock_quantity', 'reorder_point']));
        $draft->forceFill($form['extra']);
        $columns = $this->columns($draft);
        $sent = array_keys($form['product'] + $form['extra']);
        foreach (self::REVIEWED as $key) {
            if (! in_array($key, $sent, true) && array_key_exists($key, (array) ($base['product'] ?? []))) {
                $columns[$key] = $base['product'][$key];
            }
        }

        $liveVariants = collect($live['variants'])->keyBy('id');
        $rows = [];
        foreach ($form['variants'] as $row) {
            if (! empty($row['remove'])) {
                continue;
            }
            $id = (int) ($row['id'] ?? 0) ?: null;
            $staged = is_string($row['staged_image'] ?? null) && in_array($row['staged_image'], $stagedBefore, true) ? $row['staged_image'] : null;
            $rows[] = [
                'id' => $id,
                'attributes' => app(VariantService::class)->cleanAttributes(json_decode((string) $row['attributes'], true) ?: []),
                'sku' => trim((string) $row['sku']),
                'price' => isset($row['price']) && $row['price'] !== '' ? $this->money($row['price']) : null,
                'is_active' => ! empty($row['is_active']),
                'image' => $staged ?? ($id ? ($liveVariants->get($id)['image'] ?? null) : null),
                'stock_quantity' => (int) $row['stock_quantity'],
                'low_stock_threshold' => isset($row['low_stock_threshold']) && $row['low_stock_threshold'] !== '' ? (int) $row['low_stock_threshold'] : null,
            ];
        }

        $mainImage = $live['main_image'];
        if ($pending && in_array($pending->payload['main_image'] ?? null, $stagedBefore, true)) {
            $mainImage = $pending->payload['main_image']; // the waiting edit's new image stays unless replaced
        }

        return [
            'product' => $columns,
            'stock_quantity' => array_key_exists('stock_quantity', $form['product']) ? (int) $form['product']['stock_quantity'] : ($base['stock_quantity'] ?? null),
            'variants' => $columns['has_variants'] ? $rows : [],
            'multibuy' => $form['multibuy'] !== null ? $this->normaliseMultibuy($form['multibuy']) : ($base['multibuy'] ?? $live['multibuy']),
            'main_image' => $mainImage,
            'staged' => [],
        ];
    }

    // ---- Bulk edit and CSV import --------------------------------------------------------------

    /**
     * What the shop has asked for so far: the waiting edit, else the live product.
     *
     * @return array<string, mixed>
     */
    public function draft(Product $product): array
    {
        $pending = $product->pendingChange;

        return $pending ? $pending->payload : $this->snapshot($product);
    }

    /**
     * Bulk edit and CSV import: change the reviewed state with $change (it gets and returns a payload)
     * and save it as the pending request, merged into the one already waiting. Null when the result
     * is the live product again.
     *
     * @param  callable(array<string, mixed>): array<string, mixed>  $change
     */
    public function stage(Product $product, callable $change, ?User $by): ?ProductChangeRequest
    {
        $product->unsetRelation('pendingChange');
        $pending = $product->pendingChange;
        $live = $this->snapshot($product);
        $payload = $change($pending ? $pending->payload : $live);
        $payload['staged'] = $pending ? array_values(array_intersect($pending->stagedFiles(), $this->referencedFiles($payload))) : [];

        if ($pending && Arr::except($pending->payload, 'staged') == Arr::except($payload, 'staged')) {
            return $pending; // nothing more than the waiting edit already asks for
        }

        if (! $this->differs($live, $payload)) {
            if ($pending) {
                $this->withdraw($pending);
            }

            return null;
        }

        return $this->replacePending($product, $payload, $by, $pending);
    }

    // ---- Snapshot and comparing ------------------------------------------------------------------

    /**
     * The live product's reviewed state, in payload format.
     *
     * @return array<string, mixed>
     */
    public function snapshot(Product $product): array
    {
        $variants = $product->has_variants
            ? $product->variants()->ordered()->get()->map(fn (ProductVariant $v) => [
                'id' => (int) $v->id,
                'attributes' => $v->attributes_list,
                'sku' => (string) $v->sku,
                'price' => $v->price !== null ? $this->money($v->price) : null,
                'is_active' => (bool) $v->is_active,
                'image' => $v->image ?: null,
                'stock_quantity' => (int) $v->stock_quantity,
                'low_stock_threshold' => $v->low_stock_threshold,
            ])->values()->all()
            : [];

        $offer = $product->multibuy_offer_id ? MultiBuyOffer::find($product->multibuy_offer_id) : null;

        return [
            'product' => $this->columns($product),
            'stock_quantity' => (int) $product->stock_quantity,
            'variants' => $variants,
            'multibuy' => [
                'group' => $offer && $offer->isShared() ? (string) $offer->id : '',
                'group_name' => null,
                'tiers' => $offer ? $offer->tierList() : [],
            ],
            'main_image' => $product->main_image ?: null,
            'staged' => [],
        ];
    }

    /**
     * The reviewed columns, normalised (money as "12.50", dates as strings) so two states compare.
     *
     * @return array<string, mixed>
     */
    protected function columns(Product $product): array
    {
        $translations = function (string $key) use ($product): array {
            $values = array_filter($product->getTranslations($key), fn ($v) => $v !== null && $v !== '');
            ksort($values);

            return $values;
        };
        $nullableMoney = fn ($value) => $value === null || $value === '' ? null : $this->money($value);

        return [
            'name' => $translations('name'),
            'description' => $translations('description'),
            'sku' => (string) $product->sku,
            'category_id' => $product->category_id !== null ? (int) $product->category_id : null,
            'price' => $this->money($product->price),
            'compare_price' => $nullableMoney($product->compare_price),
            'sale_price' => $nullableMoney($product->sale_price),
            'flash_sale_ends_at' => $product->flash_sale_ends_at?->format('Y-m-d H:i:s'),
            'has_variants' => (bool) $product->has_variants,
            'brand' => filled($product->brand) ? trim((string) $product->brand) : null,
            'weight' => $nullableMoney($product->weight),
            'delivery_surcharge' => $this->money($product->delivery_surcharge ?? 0),
            'video_provider' => $product->getAttribute('video_provider') ?: null,
            'video_id' => $product->getAttribute('video_id') ?: null,
            'preorder_enabled' => (bool) $product->preorder_enabled,
            'preorder_ship_date' => $product->preorder_ship_date?->toDateString(),
            'preorder_limit' => $product->preorder_limit !== null ? (int) $product->preorder_limit : null,
            'preorder_note' => filled($product->getAttribute('preorder_note')) ? (string) $product->getAttribute('preorder_note') : null,
            'preorder_closes_on' => $product->preorder_closes_on?->toDateString(),
            'quote_min_quantity' => $product->getAttribute('quote_min_quantity') !== null ? (int) $product->getAttribute('quote_min_quantity') : null,
        ];
    }

    /**
     * @param  array<string, mixed>  $input  the multibuy[...] fields
     * @return array{group: string, group_name: string|null, tiers: list<array{min_qty: int, percent: float}>}
     */
    protected function normaliseMultibuy(array $input): array
    {
        $tiers = $this->multibuy->tiersFromInput((array) ($input['tiers'] ?? []));
        $group = trim((string) ($input['group'] ?? ''));
        if (ctype_digit($group) && $tiers === []) {
            $tiers = MultiBuyOffer::find((int) $group)?->tierList() ?? []; // an empty tier list keeps the group's own
        }

        return ['group' => $group, 'group_name' => $group === 'new' ? trim((string) ($input['group_name'] ?? '')) : null, 'tiers' => $tiers];
    }

    protected function money(mixed $value): string
    {
        return number_format((float) $value, 2, '.', '');
    }

    /**
     * Does the payload change anything that needs review compared with the live product?
     *
     * @param  array<string, mixed>  $live
     * @param  array<string, mixed>  $payload
     */
    public function differs(array $live, array $payload): bool
    {
        $reviewed = function (array $state): array {
            $product = Arr::only((array) $state['product'], self::REVIEWED);
            $product['brand'] = Brand::keyFor($product['brand'] ?? null); // "SAMSUNG" is the brand "Samsung"
            ksort($product);
            $multibuy = (array) ($state['multibuy'] ?? []);

            return [
                'product' => $product,
                'variants' => array_map(fn ($row) => Arr::only((array) $row, self::REVIEWED_VARIANT), array_values((array) ($state['variants'] ?? []))),
                'multibuy' => [
                    'group' => (string) ($multibuy['group'] ?? ''),
                    'group_name' => $multibuy['group_name'] ?? null,
                    'tiers' => array_map(fn ($t) => ['min_qty' => (int) $t['min_qty'], 'percent' => round((float) $t['percent'], 2)], array_values((array) ($multibuy['tiers'] ?? []))),
                ],
                'main_image' => $state['main_image'] ?? null,
            ];
        };

        return $reviewed($live) != $reviewed($payload);
    }

    /**
     * The files a payload points at.
     *
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    protected function referencedFiles(array $payload): array
    {
        $files = array_merge([$payload['main_image'] ?? null], array_column((array) ($payload['variants'] ?? []), 'image'));

        return array_values(array_filter(array_map(fn ($f) => is_string($f) ? $f : null, $files)));
    }

    // ---- The requests --------------------------------------------------------------------------

    /**
     * Save the payload as the product's pending request; the one it replaces is withdrawn and its
     * files that the new one does not keep are deleted.
     *
     * @param  array<string, mixed>  $payload
     */
    protected function replacePending(Product $product, array $payload, ?User $by, ?ProductChangeRequest $pending): ProductChangeRequest
    {
        $change = DB::transaction(function () use ($product, $payload, $by) {
            $old = ProductChangeRequest::where('product_id', $product->id)->pending()->lockForUpdate()->get();
            foreach ($old as $request) {
                $request->forceFill(['status' => ProductChangeRequest::WITHDRAWN])->save();
            }

            $change = new ProductChangeRequest(['product_id' => $product->id, 'seller_id' => $product->seller_id, 'submitted_by' => $by?->id, 'payload' => $payload]);
            $change->status = ProductChangeRequest::PENDING;
            $change->save();

            return $change;
        });

        if ($pending) {
            $this->deleteFiles(array_diff($pending->stagedFiles(), $change->stagedFiles()));
        }
        $product->setRelation('pendingChange', $change);

        return $change;
    }

    /** The shop takes back its waiting edit (or deletes the product): the live version stays. */
    public function withdraw(ProductChangeRequest $change): void
    {
        if (! $change->isPending()) {
            return;
        }
        $change->forceFill(['status' => ProductChangeRequest::WITHDRAWN])->save();
        $this->deleteFiles($change->stagedFiles());
    }

    /**
     * An admin turns the edit down: the live version stays, the edit's files are deleted and the
     * shop is emailed the reason.
     */
    public function reject(ProductChangeRequest $change, User $admin, string $reason): void
    {
        if (! $change->isPending()) {
            throw new RuntimeException(__('This edit has already been dealt with.'));
        }

        $change->forceFill([
            'status' => ProductChangeRequest::REJECTED,
            'reviewed_by' => $admin->id,
            'reviewed_at' => now(),
            'rejection_reason' => $reason,
        ])->save();
        $this->deleteFiles($change->stagedFiles());

        Audit::record('product.change_rejected', $change->product, ['change_request' => $change->id, 'reason' => $reason]);
        $change->seller?->notify(new ProductChangeReviewed($change));
    }

    /**
     * An admin approves the edit: it is applied to the live product the way the product form saves
     * one. If the product changed since in a way that clashes (a variant in the edit was deleted, a
     * SKU was taken...), nothing is applied and the exception says why.
     *
     * @throws RuntimeException
     */
    public function approve(ProductChangeRequest $change, User $admin): void
    {
        DB::transaction(function () use ($change, $admin) {
            $change = ProductChangeRequest::whereKey($change->id)->lockForUpdate()->firstOrFail();
            if (! $change->isPending()) {
                throw new RuntimeException(__('This edit has already been dealt with.'));
            }
            $product = $change->product;
            if (! $product) {
                throw new RuntimeException(__('The product has been deleted.'));
            }

            $this->checkConflicts($product, $change->payload);

            try {
                $this->editor->apply($product, $this->formFromPayload($product, $change->payload));
            } catch (Throwable $e) {
                report($e);

                throw new RuntimeException(__('The edit could not be applied, so nothing was changed. Reject it and ask the shop to edit the product again.'));
            }

            $change->forceFill(['status' => ProductChangeRequest::APPROVED, 'reviewed_by' => $admin->id, 'reviewed_at' => now()])->save();
        });

        $change->refresh();
        Audit::record('product.change_approved', $change->product, ['change_request' => $change->id]);
        $change->seller?->notify(new ProductChangeReviewed($change));
    }

    /**
     * Refuse an approval that would no longer fit the product.
     *
     * @param  array<string, mixed>  $payload
     *
     * @throws RuntimeException
     */
    protected function checkConflicts(Product $product, array $payload): void
    {
        $columns = (array) $payload['product'];
        $ids = $product->variants()->pluck('id')->map(fn ($id) => (int) $id)->all();

        foreach ((array) $payload['variants'] as $row) {
            if (! empty($row['id']) && ! in_array((int) $row['id'], $ids, true)) {
                throw new RuntimeException(__('A variant in this edit (:variant) has been deleted since the shop made it. Reject the edit and ask the shop to edit the product again.', ['variant' => implode(' / ', (array) $row['attributes'])]));
            }
            $sku = (string) $row['sku'];
            $taken = ProductVariant::where('sku', $sku)->where('product_id', '!=', $product->id)->exists()
                || Product::withTrashed()->where('sku', $sku)->exists();
            if ($taken) {
                throw new RuntimeException(__('The SKU ":sku" is already in use.', ['sku' => $sku]));
            }
        }

        if (Product::withTrashed()->where('sku', $columns['sku'])->whereKeyNot($product->id)->exists()) {
            throw new RuntimeException(__('The SKU ":sku" is already in use.', ['sku' => $columns['sku']]));
        }
        if ($columns['category_id'] && ! Category::whereKey($columns['category_id'])->exists()) {
            throw new RuntimeException(__('The category chosen in this edit no longer exists.'));
        }
        $group = (string) ($payload['multibuy']['group'] ?? '');
        if (ctype_digit($group) && ! MultiBuyOffer::where('seller_id', $product->seller_id)->whereNotNull('name')->whereKey((int) $group)->exists()) {
            throw new RuntimeException(__('The mix-and-match group chosen in this edit no longer exists.'));
        }
    }

    /**
     * The payload as a product form for ProductEditService::apply(). Stock comes from the live
     * product (it changed by itself since: orders, the stock page), except for new variants and a
     * product that stops having variants.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function formFromPayload(Product $product, array $payload): array
    {
        $columns = (array) $payload['product'];
        $extra = $columns;
        if ($product->has_variants && empty($columns['has_variants']) && isset($payload['stock_quantity'])) {
            $extra['stock_quantity'] = (int) $payload['stock_quantity'];
        }

        $live = $product->variants()->get()->keyBy('id');
        $rows = [];
        foreach ((array) $payload['variants'] as $row) {
            $variant = ! empty($row['id']) ? $live->get((int) $row['id']) : null;
            $rows[] = [
                'id' => $variant?->id,
                'attributes' => json_encode((array) $row['attributes']),
                'sku' => (string) $row['sku'],
                'price' => $row['price'],
                'stock_quantity' => $variant ? (int) $variant->stock_quantity : (int) ($row['stock_quantity'] ?? 0),
                'low_stock_threshold' => $variant ? $variant->low_stock_threshold : ($row['low_stock_threshold'] ?? null),
                'is_active' => (bool) $row['is_active'],
                'image' => $row['image'] ?? null,
            ];
        }

        $live = $this->snapshot($product);
        $multibuy = (array) ($payload['multibuy'] ?? []);
        $multibuyChanged = $this->differs(['product' => $live['product'], 'multibuy' => $live['multibuy']], ['product' => $live['product'], 'multibuy' => $multibuy]);

        return [
            'product' => [],
            'extra' => $extra,
            'variants' => empty($columns['has_variants']) ? [] : $rows,
            'multibuy' => $multibuyChanged ? $multibuy : null,
            'main_image' => in_array($payload['main_image'] ?? null, (array) ($payload['staged'] ?? []), true) ? $payload['main_image'] : null,
        ];
    }

    /**
     * @param  iterable<string>  $paths
     */
    protected function deleteFiles(iterable $paths): void
    {
        foreach ($paths as $path) {
            // Never a file the live product (or one of its variants) uses
            if (Product::withTrashed()->where('main_image', $path)->exists() || ProductVariant::where('image', $path)->exists()) {
                continue;
            }
            $this->editor->deleteStaged($path);
        }
    }

    // ---- Showing an edit -------------------------------------------------------------------------

    /**
     * The product as the waiting edit would make it, for prefilling the Seller Centre form: an
     * unsaved copy, with the variants as unsaved models. Stock is the live stock.
     *
     * @return array{product: Product, variants: \Illuminate\Support\Collection<int, ProductVariant>}
     */
    public function preview(Product $product, ProductChangeRequest $change): array
    {
        $payload = $change->payload;
        $draft = clone $product;
        $draft->forceFill(Arr::except((array) $payload['product'], ['name', 'description']));
        foreach (['name', 'description'] as $key) {
            $draft->setAttribute($key, (array) ($payload['product'][$key] ?? []));
        }
        if (($payload['main_image'] ?? null) && $payload['main_image'] !== $product->main_image) {
            $draft->setRelation('mainImage', new \App\Models\ProductImage(['url' => \Illuminate\Support\Facades\Storage::url($payload['main_image']), 'is_main' => true]));
        }
        $offer = $this->previewOffer($product, (array) ($payload['multibuy'] ?? []));
        $draft->setRelation('multibuyOffer', $offer);

        $live = $product->variants()->get()->keyBy('id');
        $staged = $change->stagedFiles();
        $variants = collect((array) $payload['variants'])->map(function ($row) use ($live, $staged) {
            $existing = ! empty($row['id']) ? $live->get((int) $row['id']) : null;
            $variant = $existing ? clone $existing : new ProductVariant;
            $variant->forceFill([
                'attributes' => (array) $row['attributes'],
                'sku' => $row['sku'],
                'price' => $row['price'],
                'is_active' => (bool) $row['is_active'],
                'image' => $row['image'] ?? null,
            ]);
            if (! $existing) {
                $variant->forceFill(['stock_quantity' => (int) ($row['stock_quantity'] ?? 0), 'low_stock_threshold' => $row['low_stock_threshold'] ?? null]);
            }
            $variant->setAttribute('staged_image', in_array($row['image'] ?? null, $staged, true) ? $row['image'] : null);

            return $variant;
        })->values();

        return ['product' => $draft, 'variants' => $variants];
    }

    /**
     * An unsaved offer that shows the edit's multi-buy fieldset (the form reads tierList() and isShared()).
     *
     * @param  array<string, mixed>  $multibuy
     */
    protected function previewOffer(Product $product, array $multibuy): ?MultiBuyOffer
    {
        $group = (string) ($multibuy['group'] ?? '');
        if (ctype_digit($group)) {
            $offer = MultiBuyOffer::where('seller_id', $product->seller_id)->find((int) $group);
            if ($offer) {
                $offer = clone $offer;
                $offer->tiers = (array) ($multibuy['tiers'] ?? []);
            }

            return $offer;
        }
        if (($multibuy['tiers'] ?? []) === []) {
            return null;
        }

        return new MultiBuyOffer(['name' => null, 'tiers' => (array) $multibuy['tiers']]);
    }

    /**
     * Before → after of every reviewed field the edit changes, for the admin review page. Variants
     * are compared by id: added (no id), removed (id missing from the edit) or changed.
     *
     * @return array{fields: list<array{label: string, before: string|null, after: string|null, type: string}>, variants: list<array{status: string, before: array<string, mixed>|null, after: array<string, mixed>|null}>}
     */
    public function changes(ProductChangeRequest $change): array
    {
        $product = $change->product;
        $live = $product ? $this->snapshot($product) : ['product' => [], 'variants' => [], 'multibuy' => [], 'main_image' => null];
        $payload = $change->payload;
        $before = (array) $live['product'];
        $after = (array) $payload['product'];

        $fields = [];
        $add = function (string $label, $from, $to, string $type = 'text') use (&$fields) {
            if ($from != $to) {
                $fields[] = ['label' => $label, 'before' => $from === null || $from === '' ? null : (string) $from, 'after' => $to === null || $to === '' ? null : (string) $to, 'type' => $type];
            }
        };
        $categoryName = function ($id): ?string {
            $category = $id ? Category::find($id) : null;

            return $category ? $category->name : ($id ? '#'.$id : null);
        };
        $video = fn ($cols) => ($cols['video_provider'] ?? null) ? \App\Support\ProductVideo::make($cols['video_provider'], $cols['video_id'] ?? null)?->watchUrl() : null;
        $preorder = fn ($cols) => ! empty($cols['preorder_enabled'])
            ? __('On: ships around :date, up to :limit units', ['date' => $cols['preorder_ship_date'] ?? '—', 'limit' => $cols['preorder_limit'] ?? '—'])
                .(! empty($cols['preorder_closes_on']) ? ', '.__('closes :date', ['date' => $cols['preorder_closes_on']]) : '')
                .(! empty($cols['preorder_note']) ? ' – '.$cols['preorder_note'] : '')
            : __('Off');

        $add(__('Name (English)'), $before['name']['en'] ?? null, $after['name']['en'] ?? null);
        $add(__('Name (Dhivehi)'), $before['name']['dv'] ?? null, $after['name']['dv'] ?? null, 'rtl');
        $add(__('Description (English)'), $before['description']['en'] ?? null, $after['description']['en'] ?? null, 'long');
        $add(__('Description (Dhivehi)'), $before['description']['dv'] ?? null, $after['description']['dv'] ?? null, 'long-rtl');
        $add(__('SKU'), $before['sku'] ?? null, $after['sku'] ?? null);
        $add(__('Category'), $categoryName($before['category_id'] ?? null), $categoryName($after['category_id'] ?? null));
        $add(__('Price'), $before['price'] ?? null, $after['price'] ?? null);
        $add(__('Compare-at price'), $before['compare_price'] ?? null, $after['compare_price'] ?? null);
        $add(__('Sale price'), $before['sale_price'] ?? null, $after['sale_price'] ?? null);
        $add(__('Sale ends'), $before['flash_sale_ends_at'] ?? null, $after['flash_sale_ends_at'] ?? null);
        if (Brand::keyFor($before['brand'] ?? null) !== Brand::keyFor($after['brand'] ?? null)) {
            $add(__('Brand'), $before['brand'] ?? null, $after['brand'] ?? null);
        }
        $add(__('Weight (kg)'), $before['weight'] ?? null, $after['weight'] ?? null);
        $add(__('Extra delivery charge per item (MVR)'), $before['delivery_surcharge'] ?? null, $after['delivery_surcharge'] ?? null);
        $add(__('Sold in variants'), isset($before['has_variants']) ? ($before['has_variants'] ? __('Yes') : __('No')) : null, ($after['has_variants'] ?? false) ? __('Yes') : __('No'));
        $add(__('Video link'), $video($before), $video($after), 'link');
        $add(__('Pre-order'), $preorder($before), $preorder($after));
        $add(__('Smallest quantity for a quote'), $before['quote_min_quantity'] ?? null, $after['quote_min_quantity'] ?? null);
        $add(__('Multi-buy offer'), $this->describeMultibuy((array) $live['multibuy']), $this->describeMultibuy((array) ($payload['multibuy'] ?? [])));
        if (($payload['main_image'] ?? null) && ($payload['main_image'] ?? null) !== $live['main_image']) {
            $fields[] = ['label' => __('Main image'), 'before' => $live['main_image'], 'after' => $payload['main_image'], 'type' => 'image'];
        }

        $liveRows = collect((array) $live['variants'])->keyBy('id');
        $variants = [];
        $kept = [];
        foreach ((array) $payload['variants'] as $row) {
            $old = ! empty($row['id']) ? $liveRows->get((int) $row['id']) : null;
            if ($old) {
                $kept[(int) $row['id']] = true;
            }
            $status = ! $old ? 'added' : (Arr::only($old, self::REVIEWED_VARIANT) != Arr::only($row, self::REVIEWED_VARIANT) ? 'changed' : 'same');
            $variants[] = ['status' => $status, 'before' => $old, 'after' => $row];
        }
        foreach ($liveRows as $id => $row) {
            if (! isset($kept[$id])) {
                $variants[] = ['status' => 'removed', 'before' => $row, 'after' => null];
            }
        }

        return ['fields' => $fields, 'variants' => $variants];
    }

    /**
     * "Buy 2, save 5% · Buy 3, save 10% (group: T-shirts)", or null for no offer.
     *
     * @param  array<string, mixed>  $multibuy
     */
    protected function describeMultibuy(array $multibuy): ?string
    {
        $tiers = array_map(fn ($t) => MultiBuyService::tierLabel(['min_qty' => (int) $t['min_qty'], 'percent' => (float) $t['percent']]), (array) ($multibuy['tiers'] ?? []));
        $group = (string) ($multibuy['group'] ?? '');
        $groupName = $group === 'new' ? (string) ($multibuy['group_name'] ?? '') : (ctype_digit($group) ? (string) MultiBuyOffer::find((int) $group)?->name : '');
        if ($tiers === [] && $groupName === '') {
            return null;
        }

        return implode(' · ', $tiers).($groupName !== '' ? ' ('.__('mix-and-match group: :name', ['name' => $groupName]).($group === 'new' ? ', '.__('new') : '').')' : '');
    }
}
