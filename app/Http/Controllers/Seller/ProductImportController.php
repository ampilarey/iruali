<?php

namespace App\Http\Controllers\Seller;

use App\Http\Controllers\Controller;
use App\Services\ProductCsvService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * CSV export and import of the shop's catalogue (dry run first, then apply).
 */
class ProductImportController extends Controller
{
    public const DISK = 'local';

    public function __construct(protected ProductCsvService $csv) {}

    public function export()
    {
        $name = 'products-'.Str::slug(Auth::user()->business_name ?: Auth::user()->name).'-'.now()->format('Y-m-d').'.csv';

        return response($this->csv->export(Auth::user()), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="'.$name.'"',
        ]);
    }

    public function sample()
    {
        return response($this->csv->sample(), 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="iruali-products-sample.csv"',
        ]);
    }

    public function form()
    {
        return view('seller.products.import', ['preview' => null, 'token' => null, 'result' => session('import_result')]);
    }

    /**
     * Dry run: show what each row would do, and keep the file for "Apply".
     */
    public function preview(Request $request)
    {
        $request->validate(['file' => 'required|file|mimes:csv,txt|max:5120']);

        $contents = (string) file_get_contents($request->file('file')->getRealPath());
        $parsed = $this->csv->parse($contents);
        if ($parsed['errors']) {
            return back()->withErrors(['file' => $parsed['errors']]);
        }

        $token = Str::random(32);
        Storage::disk(self::DISK)->put($this->path($token), $contents);

        return view('seller.products.import', [
            'preview' => $this->csv->preview(Auth::user(), $parsed['rows']),
            'token' => $token,
            'result' => null,
        ]);
    }

    /**
     * Apply the file previewed under this token (valid rows only).
     */
    public function apply(Request $request)
    {
        $request->validate(['token' => 'required|string|size:32|alpha_num']);

        $path = $this->path($request->token);
        $disk = Storage::disk(self::DISK);
        abort_unless($disk->exists($path), 404);

        $parsed = $this->csv->parse((string) $disk->get($path));
        $disk->delete($path);
        if ($parsed['errors']) {
            return redirect()->route('seller.products.import')->withErrors(['file' => $parsed['errors']]);
        }

        $counts = $this->csv->apply(Auth::user(), $parsed['rows']);

        return redirect()->route('seller.products.import')->with('import_result', $counts);
    }

    /**
     * Previewed files live under the shop's own folder, so a token can't reach another shop's upload.
     */
    protected function path(string $token): string
    {
        return 'imports/'.Auth::id().'/'.$token.'.csv';
    }
}
