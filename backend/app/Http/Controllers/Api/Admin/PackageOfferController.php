<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\PackageOffer;
use App\Services\Travel\PackageCatalog;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class PackageOfferController extends Controller
{
    public const CSV_COLUMNS = ['supplier_reference', 'title_hy', 'title_ru', 'title_en', 'destination_code', 'origin', 'departure_date', 'nights', 'adults', 'children', 'hotel_name', 'stars', 'room_type', 'meal_plan', 'inclusions', 'price', 'currency', 'price_basis', 'valid_until', 'terms_hy', 'exclusions_hy'];

    public function index(Request $request)
    {
        $data = $request->validate(['search' => ['nullable', 'string', 'max:120'], 'provider' => ['nullable', Rule::in(['local', ...PackageCatalog::PROVIDERS])], 'status' => ['nullable', 'in:draft,published,archived'], 'page' => ['nullable', 'integer', 'min:1', 'max:1000']]);
        $query = PackageOffer::query();
        if (filled($data['search'] ?? null)) {
            $query->where(fn ($q) => $q->where('title->hy', 'like', '%'.$data['search'].'%')->orWhere('supplier_reference', 'like', '%'.$data['search'].'%')->orWhere('hotel_name', 'like', '%'.$data['search'].'%'));
        }
        if (filled($data['provider'] ?? null)) {
            $query->where('provider_code', $data['provider']);
        }
        if (filled($data['status'] ?? null)) {
            $query->where('status', $data['status']);
        }

        return $query->latest('id')->paginate(15);
    }

    public function show(int $id)
    {
        return PackageOffer::findOrFail($id);
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);

        return response()->json(PackageOffer::create($data), 201);
    }

    public function update(Request $request, int $id)
    {
        $request->validate(['version' => ['required', 'integer', 'min:1']]);
        $data = $this->validated($request, $id);

        return DB::transaction(function () use ($id, $data, $request) {
            $offer = PackageOffer::lockForUpdate()->findOrFail($id);
            abort_unless($offer->version === (int) $request->input('version'), 409, 'Առաջարկն արդեն փոփոխվել է։ Թարմացրեք էջը։');
            $offer->fill([...$data, 'version' => $offer->version + 1])->save();

            return $offer;
        });
    }

    private function validated(Request $request, ?int $id = null): array
    {
        $rules = PackageCatalog::rules();
        $rules['supplier_reference'][] = Rule::unique('package_offers')->where('provider_code', $request->input('provider_code'))->ignore($id);
        $data = $request->validate($rules);
        sort($data['children']);
        PackageCatalog::validatePublication($data);

        return $data;
    }

    public function template()
    {
        return response(implode(',', self::CSV_COLUMNS)."\r\n", 200, ['Content-Type' => 'text/csv; charset=UTF-8', 'Content-Disposition' => 'attachment; filename="govista-packages-template.csv"']);
    }

    public function preview(Request $request)
    {
        $data = $request->validate(['provider_code' => ['required', Rule::in(['local', ...PackageCatalog::PROVIDERS])], 'csv' => ['required', 'string', 'max:500000']]);
        if (! mb_check_encoding($data['csv'], 'UTF-8')) {
            throw ValidationException::withMessages(['csv' => ['Ֆայլը պետք է լինի UTF-8 ձևաչափով։']]);
        }
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, preg_replace('/^\xEF\xBB\xBF/', '', $data['csv']));
        rewind($stream);
        try {
            $header = fgetcsv($stream, 0, ',', '"', '');
            if (! $header || count(array_unique($header)) !== count($header) || array_diff($header, self::CSV_COLUMNS) || array_diff(['supplier_reference', 'title_hy', 'destination_code'], $header)) {
                throw ValidationException::withMessages(['csv' => ['Սյունակները չեն համապատասխանում ձևանմուշին։']]);
            }
            $rows = [];
            $errors = [];
            $seen = [];
            $line = 1;
            while (($values = fgetcsv($stream, 0, ',', '"', '')) !== false) {
                $line++;
                if ($values === [null]) {
                    continue;
                }
                if ($line > 101) {
                    throw ValidationException::withMessages(['csv' => ['Մեկ ներմուծումը առավելագույնը 100 տող է։']]);
                }
                if (count($values) !== count($header)) {
                    $errors[] = ['row' => $line, 'messages' => ['Սյունակների թիվը սխալ է։']];

                    continue;
                }
                $row = array_map('trim', array_combine($header, $values));
                $offer = [
                    'provider_code' => $data['provider_code'], 'supplier_reference' => $row['supplier_reference'],
                    'title' => ['hy' => $row['title_hy'], 'ru' => $row['title_ru'] ?? '', 'en' => $row['title_en'] ?? ''],
                    'description' => [], 'terms' => ['hy' => $row['terms_hy'] ?? ''], 'exclusions' => ['hy' => $row['exclusions_hy'] ?? ''],
                    'destination_code' => $row['destination_code'], 'origin' => ($row['origin'] ?? '') ?: 'EVN',
                    'hotel_name' => ($row['hotel_name'] ?? '') ?: null, 'stars' => ($row['stars'] ?? '') ?: null, 'room_type' => ($row['room_type'] ?? '') ?: null,
                    'meal_plan' => ($row['meal_plan'] ?? '') ?: 'RO', 'departure_date' => ($row['departure_date'] ?? '') ?: null,
                    'nights' => ($row['nights'] ?? '') ?: 7, 'adults' => ($row['adults'] ?? '') ?: 2,
                    'children' => ($row['children'] ?? '') === '' ? [] : explode('|', $row['children']),
                    'inclusions' => ($row['inclusions'] ?? '') === '' ? [] : explode('|', $row['inclusions']),
                    'price' => ($row['price'] ?? '') ?: null, 'currency' => ($row['currency'] ?? '') ?: 'AMD',
                    'price_basis' => ($row['price_basis'] ?? '') ?: 'party_total', 'valid_until' => ($row['valid_until'] ?? '') ?: null,
                    'price_checked_at' => null, 'status' => 'draft', 'availability' => 'on_request',
                ];
                $validator = Validator::make($offer, PackageCatalog::rules());
                $messages = $validator->errors()->all();
                $reference = mb_strtolower($offer['supplier_reference']);
                if (isset($seen[$reference]) || PackageOffer::where('provider_code', $data['provider_code'])->where('supplier_reference', $offer['supplier_reference'])->exists()) {
                    $messages[] = 'Այս մատակարարի առաջարկի համարը կրկնվում է։ Գործող առաջարկը չի վերագրվի։';
                }
                $seen[$reference] = true;
                if ($messages) {
                    $errors[] = ['row' => $line, 'messages' => $messages];

                    continue;
                }
                $offer['children'] = array_map('intval', $offer['children']);
                sort($offer['children']);
                $rows[] = $offer;
            }
            if (! $rows && ! $errors) {
                throw ValidationException::withMessages(['csv' => ['Ֆայլում տվյալների տողեր չկան։']]);
            }
            $token = $errors ? null : Str::random(48);
            if ($token) {
                Cache::put('package-import:'.$token, ['user_id' => $request->user()->id, 'rows' => $rows], now()->addMinutes(10));
            }

            return ['token' => $token, 'rows' => $rows, 'errors' => $errors, 'count' => count($rows), 'expires_in' => 600];
        } finally {
            fclose($stream);
        }
    }

    public function commit(Request $request)
    {
        $token = $request->validate(['token' => ['required', 'regex:/^[a-zA-Z0-9]{48}$/']])['token'];
        $result = Cache::lock('package-import-lock:'.$token, 20)->get(function () use ($token, $request) {
            $key = 'package-import:'.$token;
            $batch = Cache::get($key);
            abort_unless($batch && $batch['user_id'] === $request->user()->id, 410, 'Նախադիտումը ժամկետանց է։ Կրկին ստուգեք ֆայլը։');
            if (isset($batch['result'])) {
                return $batch['result'];
            }
            try {
                $ids = DB::transaction(function () use ($batch) {
                    $ids = [];
                    foreach ($batch['rows'] as $row) {
                        abort_if(PackageOffer::where('provider_code', $row['provider_code'])->where('supplier_reference', $row['supplier_reference'])->exists(), 409, 'Նախադիտումից հետո հայտնվել է նույն համարով առաջարկ։ Կրկին ստուգեք ֆայլը։');
                        $ids[] = PackageOffer::create($row)->id;
                    }

                    return $ids;
                });
            } catch (UniqueConstraintViolationException) {
                abort(409, 'Նույն համարով առաջարկն արդեն ներմուծվել է։ Կրկին ստուգեք ֆայլը։');
            }
            $result = ['created' => count($ids), 'ids' => $ids];
            Cache::put($key, ['user_id' => $batch['user_id'], 'result' => $result], now()->addMinutes(10));

            return $result;
        });
        abort_if($result === false, 409, 'Ներմուծումն արդեն ընթացքի մեջ է։');

        return $result;
    }
}
