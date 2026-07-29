<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\ContactMessage;
use App\Models\Destination;
use App\Models\Faq;
use App\Models\Page;
use App\Models\Post;
use App\Models\Service;
use App\Models\Setting;
use App\Models\Testimonial;
use App\Models\Tour;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;

class ContentController extends Controller
{
    private const RESOURCES = [
        'tours' => Tour::class,
        'destinations' => Destination::class,
        'services' => Service::class,
        'posts' => Post::class,
        'pages' => Page::class,
        'testimonials' => Testimonial::class,
        'faqs' => Faq::class,
        'settings' => Setting::class,
        'bookings' => Booking::class,
        'contact-messages' => ContactMessage::class,
    ];

    public function index(Request $request, string $resource): JsonResponse
    {
        $model = $this->model($resource);
        $query = $model::query()->latest('id');

        if ($request->filled('search')) {
            $search = '%'.$request->string('search')->toString().'%';
            $table = (new $model)->getTable();

            $query->where(function ($builder) use ($table, $search) {
                foreach (['slug', 'name', 'email', 'reference', 'key', 'item_title'] as $column) {
                    if (Schema::hasColumn($table, $column)) {
                        $builder->orWhere($column, 'like', $search);
                    }
                }
            });
        }

        $perPage = max(1, min($request->integer('per_page', 20), 100));
        $pagination = $query->paginate($perPage);

        return response()->json($pagination);
    }

    public function show(string $resource, int $id): JsonResponse
    {
        $model = $this->model($resource);

        return response()->json($model::query()->findOrFail($id));
    }

    public function store(Request $request, string $resource): JsonResponse
    {
        $model = $this->model($resource);
        $record = $model::query()->create($request->validate($this->rules($resource)));

        return response()->json($record, 201);
    }

    public function update(Request $request, string $resource, int $id): JsonResponse
    {
        $model = $this->model($resource);
        /** @var Model $record */
        $record = $model::query()->findOrFail($id);
        $record->update($request->validate($this->rules($resource, $id)));

        return response()->json($record->fresh());
    }

    public function destroy(string $resource, int $id): JsonResponse
    {
        $model = $this->model($resource);
        $model::query()->findOrFail($id)->delete();

        return response()->json(['message' => 'Գրառումը ջնջված է։']);
    }

    private function model(string $resource): string
    {
        abort_unless(isset(self::RESOURCES[$resource]), 404);

        return self::RESOURCES[$resource];
    }

    private function rules(string $resource, ?int $id = null): array
    {
        $translated = ['required', 'array:hy,ru,en'];
        $translatedNullable = ['nullable', 'array:hy,ru,en'];
        $active = ['nullable', 'boolean'];

        return match ($resource) {
            'tours' => [
                'slug' => ['required', 'alpha_dash', 'max:190', Rule::unique('tours', 'slug')->ignore($id)],
                'travel_scope' => ['required', Rule::in(['domestic', 'international'])],
                'type' => ['required', Rule::in(['group', 'private', 'package'])],
                'title' => $translated,
                'subtitle' => $translatedNullable,
                'description' => $translated,
                'location' => $translatedNullable,
                'duration' => $translatedNullable,
                'price' => ['required', 'numeric', 'min:0'],
                'old_price' => ['nullable', 'numeric', 'min:0'],
                'currency' => ['required', Rule::in(['AMD', 'USD', 'EUR', 'RUB'])],
                'rating' => ['nullable', 'numeric', 'between:0,5'],
                'review_count' => ['nullable', 'integer', 'min:0'],
                'image' => ['nullable', 'string', 'max:1000'],
                'gallery' => ['nullable', 'array'],
                'highlights' => $translatedNullable,
                'itinerary' => $translatedNullable,
                'included' => $translatedNullable,
                'excluded' => $translatedNullable,
                'featured' => $active,
                'active' => $active,
                'sort_order' => ['nullable', 'integer', 'min:0'],
            ],
            'destinations' => [
                'slug' => ['required', 'alpha_dash', 'max:190', Rule::unique('destinations', 'slug')->ignore($id)],
                'title' => $translated,
                'description' => $translated,
                'region' => $translatedNullable,
                'image' => ['nullable', 'string', 'max:1000'],
                'gallery' => ['nullable', 'array'],
                'featured' => $active,
                'active' => $active,
                'sort_order' => ['nullable', 'integer', 'min:0'],
            ],
            'services' => [
                'slug' => ['required', 'alpha_dash', 'max:190', Rule::unique('services', 'slug')->ignore($id)],
                'type' => ['required', Rule::in(['accommodation', 'transport', 'events', 'custom'])],
                'title' => $translated,
                'description' => $translated,
                'location' => $translatedNullable,
                'icon' => ['nullable', 'string', 'max:80'],
                'image' => ['nullable', 'string', 'max:1000'],
                'gallery' => ['nullable', 'array'],
                'price_from' => ['nullable', 'numeric', 'min:0'],
                'currency' => ['required', Rule::in(['AMD', 'USD', 'EUR', 'RUB'])],
                'unit' => $translatedNullable,
                'rating' => ['nullable', 'numeric', 'min:0', 'max:5'],
                'review_count' => ['nullable', 'integer', 'min:0'],
                'features' => $translatedNullable,
                'featured' => $active,
                'active' => $active,
                'sort_order' => ['nullable', 'integer', 'min:0'],
            ],
            'posts' => [
                'slug' => ['required', 'alpha_dash', 'max:190', Rule::unique('posts', 'slug')->ignore($id)],
                'category' => ['nullable', 'string', 'max:120'],
                'title' => $translated,
                'excerpt' => $translated,
                'content' => $translated,
                'image' => ['nullable', 'string', 'max:1000'],
                'reading_time' => ['nullable', 'integer', 'min:1'],
                'published_at' => ['nullable', 'date'],
                'featured' => $active,
                'active' => $active,
            ],
            'pages' => [
                'slug' => ['required', 'alpha_dash', 'max:190', Rule::unique('pages', 'slug')->ignore($id)],
                'title' => $translated,
                'content' => $translated,
                'seo_title' => $translatedNullable,
                'seo_description' => $translatedNullable,
                'image' => ['nullable', 'string', 'max:1000'],
                'active' => $active,
            ],
            'testimonials' => [
                'name' => ['required', 'string', 'max:120'],
                'country' => $translatedNullable,
                'message' => $translated,
                'avatar' => ['nullable', 'string', 'max:1000'],
                'rating' => ['nullable', 'integer', 'between:1,5'],
                'source' => ['nullable', 'string', 'max:80'],
                'active' => $active,
                'sort_order' => ['nullable', 'integer', 'min:0'],
            ],
            'faqs' => [
                'question' => $translated,
                'answer' => $translated,
                'category' => ['nullable', 'string', 'max:80'],
                'active' => $active,
                'sort_order' => ['nullable', 'integer', 'min:0'],
            ],
            'settings' => [
                'group' => ['required', 'string', 'max:80'],
                'key' => ['required', 'alpha_dash', 'max:190', Rule::unique('settings', 'key')->ignore($id)],
                'value' => ['nullable'],
                'type' => ['nullable', 'string', 'max:40'],
            ],
            'bookings' => [
                'status' => ['required', Rule::in(['new', 'confirmed', 'completed', 'cancelled'])],
                'message' => ['nullable', 'string', 'max:3000'],
                'total_price' => ['nullable', 'numeric', 'min:0'],
            ],
            'contact-messages' => [
                'status' => ['required', Rule::in(['new', 'read', 'replied', 'archived'])],
            ],
            default => [],
        };
    }
}
