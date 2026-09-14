<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Password as PasswordBroker;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class CustomerController extends Controller
{
    public function forgotPassword(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email', 'max:190'], 'locale' => ['required', 'in:hy,ru,en']]);
        abort_if(in_array(config('mail.default'), ['log', 'array']), 503, 'Email delivery is not configured. Please contact GoVista.');
        ResetPassword::createUrlUsing(fn ($user, $token) => rtrim(config('travel.frontend_url'), '/').'/'.$data['locale'].'/account?'.http_build_query(['reset_token' => $token, 'email' => $user->email]));
        PasswordBroker::sendResetLink(['email' => mb_strtolower(trim($data['email'])), 'role' => 'customer']);

        return response()->json(['message' => 'If this account exists, a reset link has been sent.']);
    }

    public function resetPassword(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email'], 'token' => ['required', 'string', 'max:255'], 'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers(), 'max:128']]);
        $status = PasswordBroker::reset([...$data, 'password_confirmation' => $request->input('password_confirmation'), 'role' => 'customer'], function (User $user, string $password) {
            $user->forceFill(['password' => $password, 'remember_token' => Str::random(60)])->save();
            $user->tokens()->delete();
        });
        if ($status !== PasswordBroker::PasswordReset) {
            throw ValidationException::withMessages(['token' => ['Invalid or expired reset link.']]);
        }

        return response()->json(['message' => 'Password updated.']);
    }

    public function register(Request $request)
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email', 'max:190', 'unique:users,email'],
            'password' => ['required', 'confirmed', Password::min(10)->letters()->numbers(), 'max:128'],
        ]);
        $user = User::create([...$data, 'role' => 'customer']);

        return response()->json($this->session($user), 201);
    }

    public function login(Request $request)
    {
        $data = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string', 'max:128']]);
        $user = User::where('email', mb_strtolower(trim($data['email'])))->where('role', 'customer')->first();
        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages(['email' => ['Մուտքային տվյալները սխալ են։ / Invalid credentials.']]);
        }

        return response()->json($this->session($user));
    }

    private function session(User $user): array
    {
        $user->tokens()->where('name', 'govista-customer')->delete();

        return ['token' => $user->createToken('govista-customer', ['customer'], now()->addHours(12))->plainTextToken, 'user' => $user->only(['id', 'name', 'email'])];
    }

    public function me(Request $request)
    {
        return $request->user()->only(['id', 'name', 'email']);
    }

    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()?->delete();

        return response()->noContent();
    }

    public function orders(Request $request)
    {
        $orders = Booking::where('user_id', $request->user()->id)->latest('id')->paginate(12);

        return response()->json($orders->through(fn ($order) => $order->customerData()));
    }

    public function action(Request $request, int $id)
    {
        $data = $request->validate(['action' => ['required', 'in:accept,cancel'], 'version' => ['required', 'integer', 'min:1']]);

        return DB::transaction(function () use ($request, $id, $data) {
            $order = Booking::where('user_id', $request->user()->id)->lockForUpdate()->findOrFail($id);
            abort_if($order->version !== $data['version'], 409, 'Հայտը փոփոխվել է։ Թարմացրեք էջը։');
            if ($data['action'] === 'accept') {
                abort_unless($order->status === 'quoted' && $order->quote_expires_at?->isFuture(), 409, 'Առաջարկը այլևս հասանելի չէ։');
                $status = 'accepted';
            } else {
                abort_unless(in_array($order->status, ['new', 'reviewing', 'quoted', 'accepted', 'confirmed']), 409, 'Այս գործողությունն անհասանելի է։');
                $status = in_array($order->status, ['accepted', 'confirmed']) ? 'cancellation_requested' : 'cancelled';
            }
            $order->update(['status' => $status, 'version' => $order->version + 1]);
            $order->events()->create(['actor_id' => $request->user()->id, 'status' => $status]);

            return response()->json($order->customerData());
        });
    }
}
