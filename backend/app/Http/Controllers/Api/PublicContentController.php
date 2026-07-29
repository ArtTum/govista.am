<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Destination;
use App\Models\Faq;
use App\Models\Page;
use App\Models\Post;
use App\Models\Service;
use App\Models\Setting;
use App\Models\Testimonial;
use App\Models\Tour;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class PublicContentController extends Controller
{
    public function home(Request $request): JsonResponse
    {
        $locale = $this->locale($request);

        return response()->json([
            'settings' => $this->settings($locale),
            'featured_tours' => $this->localized(
                Tour::query()->where('active', true)->where('featured', true)->orderBy('sort_order')->take(6)->get(),
                $locale
            ),
            'domestic_tours' => $this->localized(
                Tour::query()->where('active', true)->where('travel_scope', 'domestic')->orderByDesc('featured')->orderBy('sort_order')->take(3)->get(),
                $locale
            ),
            'international_tours' => $this->localized(
                Tour::query()->where('active', true)->where('travel_scope', 'international')->orderByDesc('featured')->orderBy('sort_order')->take(3)->get(),
                $locale
            ),
            'accommodations' => $this->localized(
                Service::query()->where('active', true)->where('type', 'accommodation')->orderByDesc('featured')->orderBy('sort_order')->take(4)->get(),
                $locale
            ),
            'cars' => $this->localized(
                Service::query()->where('active', true)->where('type', 'transport')->orderByDesc('featured')->orderBy('sort_order')->take(4)->get(),
                $locale
            ),
            'destinations' => $this->localized(
                Destination::query()->where('active', true)->where('featured', true)->orderBy('sort_order')->take(8)->get(),
                $locale
            ),
            'services' => $this->localized(
                Service::query()->where('active', true)->orderByDesc('featured')->orderBy('sort_order')->get(),
                $locale
            ),
            'posts' => $this->localized(
                Post::query()->where('active', true)->whereNotNull('published_at')->latest('published_at')->take(3)->get(),
                $locale
            ),
            'testimonials' => $this->localized(
                Testimonial::query()->where('active', true)->orderBy('sort_order')->get(),
                $locale
            ),
            'faqs' => $this->localized(
                Faq::query()->where('active', true)->orderBy('sort_order')->take(6)->get(),
                $locale
            ),
        ]);
    }

    public function tours(Request $request): JsonResponse
    {
        $query = Tour::query()->where('active', true)->orderBy('sort_order');

        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }

        if ($request->filled('travel_scope')) {
            $scope = $request->string('travel_scope')->toString();

            if (in_array($scope, ['domestic', 'international'], true)) {
                $query->where('travel_scope', $scope);
            }
        }

        if ($request->boolean('featured')) {
            $query->where('featured', true);
        }

        return $this->paginated($query, $request);
    }

    public function tour(Request $request, string $slug): JsonResponse
    {
        $tour = Tour::query()->where('slug', $slug)->where('active', true)->firstOrFail();

        return response()->json([
            'tour' => $tour->toLocalizedArray($this->locale($request)),
            'related' => $this->localized(
                Tour::query()
                    ->where('active', true)
                    ->where('id', '!=', $tour->id)
                    ->where('travel_scope', $tour->travel_scope)
                    ->where('type', $tour->type)
                    ->orderByDesc('featured')
                    ->take(3)
                    ->get(),
                $this->locale($request)
            ),
        ]);
    }

    public function destinations(Request $request): JsonResponse
    {
        return $this->paginated(
            Destination::query()->where('active', true)->orderBy('sort_order'),
            $request
        );
    }

    public function destination(Request $request, string $slug): JsonResponse
    {
        $destination = Destination::query()->where('slug', $slug)->where('active', true)->firstOrFail();

        return response()->json($destination->toLocalizedArray($this->locale($request)));
    }

    public function services(Request $request): JsonResponse
    {
        $query = Service::query()->where('active', true)->orderBy('sort_order');

        if ($request->filled('type')) {
            $query->where('type', $request->string('type'));
        }

        return response()->json($this->localized($query->get(), $this->locale($request)));
    }

    public function service(Request $request, string $slug): JsonResponse
    {
        $service = Service::query()->where('slug', $slug)->where('active', true)->firstOrFail();

        return response()->json([
            'service' => $service->toLocalizedArray($this->locale($request)),
            'related' => $this->localized(
                Service::query()
                    ->where('active', true)
                    ->where('id', '!=', $service->id)
                    ->where('type', $service->type)
                    ->orderByDesc('featured')
                    ->orderBy('sort_order')
                    ->take(3)
                    ->get(),
                $this->locale($request)
            ),
        ]);
    }

    public function posts(Request $request): JsonResponse
    {
        return $this->paginated(
            Post::query()->where('active', true)->whereNotNull('published_at')->latest('published_at'),
            $request
        );
    }

    public function post(Request $request, string $slug): JsonResponse
    {
        $post = Post::query()->where('slug', $slug)->where('active', true)->firstOrFail();

        return response()->json($post->toLocalizedArray($this->locale($request)));
    }

    public function page(Request $request, string $slug): JsonResponse
    {
        $page = Page::query()->where('slug', $slug)->where('active', true)->firstOrFail();

        return response()->json($page->toLocalizedArray($this->locale($request)));
    }

    private function paginated(Builder $query, Request $request): JsonResponse
    {
        $locale = $this->locale($request);
        $perPage = max(1, min($request->integer('per_page', 12), 48));
        $pagination = $query->paginate($perPage);

        return response()->json([
            'data' => $this->localized(collect($pagination->items()), $locale),
            'meta' => [
                'current_page' => $pagination->currentPage(),
                'last_page' => $pagination->lastPage(),
                'per_page' => $pagination->perPage(),
                'total' => $pagination->total(),
            ],
        ]);
    }

    private function localized(iterable $items, string $locale): array
    {
        return collect($items)
            ->map(fn ($item) => $item->toLocalizedArray($locale))
            ->values()
            ->all();
    }

    private function settings(string $locale): array
    {
        return Setting::query()->get()->mapWithKeys(function (Setting $setting) use ($locale) {
            $value = $setting->value;

            if (is_array($value) && array_intersect(['hy', 'ru', 'en'], array_keys($value))) {
                $value = $value[$locale] ?? $value['hy'] ?? $value['en'] ?? reset($value);
            }

            return [$setting->key => $value];
        })->all();
    }

    private function locale(Request $request): string
    {
        $locale = $request->string('locale')->toString();

        return in_array($locale, ['hy', 'ru', 'en'], true) ? $locale : 'hy';
    }
}
