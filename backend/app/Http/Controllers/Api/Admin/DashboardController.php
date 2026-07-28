<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\ContactMessage;
use App\Models\Destination;
use App\Models\Post;
use App\Models\Service;
use App\Models\Tour;
use Illuminate\Http\JsonResponse;

class DashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json([
            'stats' => [
                ['key' => 'tours', 'label' => 'Տուրեր', 'value' => Tour::count()],
                ['key' => 'destinations', 'label' => 'Ուղղություններ', 'value' => Destination::count()],
                ['key' => 'bookings', 'label' => 'Նոր ամրագրումներ', 'value' => Booking::where('status', 'new')->count()],
                ['key' => 'messages', 'label' => 'Նոր նամակներ', 'value' => ContactMessage::where('status', 'new')->count()],
            ],
            'content' => [
                'services' => Service::count(),
                'posts' => Post::count(),
            ],
            'latest_bookings' => Booking::query()->latest()->take(6)->get(),
            'latest_messages' => ContactMessage::query()->latest()->take(6)->get(),
        ]);
    }
}
