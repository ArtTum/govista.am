<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class TravelRequestController extends Controller
{
    public function index(Request $request)
    {
        $data = $request->validate(['status' => ['nullable', 'string', 'max:40'], 'search' => ['nullable', 'string', 'max:120'], 'page' => ['nullable', 'integer', 'min:1']]);

        return Booking::query()->when($data['status'] ?? null, fn ($q, $s) => $q->where('status', $s))
            ->when($data['search'] ?? null, fn ($q, $s) => $q->where(fn ($q) => $q->where('reference', 'like', "%$s%")->orWhere('name', 'like', "%$s%")->orWhere('email', 'like', "%$s%")))
            ->latest('id')->paginate(20);
    }

    public function show(int $id)
    {
        return Booking::with(['events' => fn ($q) => $q->oldest('id')])->findOrFail($id);
    }

    public function update(Request $request, int $id)
    {
        $data = $request->validate([
            'version' => ['required', 'integer', 'min:1'],
            'action' => ['required', 'in:review,quote,accept_guest,confirm,complete,cancel,note,component'],
            'note' => ['nullable', 'string', 'max:3000'],
            'internal' => ['sometimes', 'boolean'],
            'total_price' => ['required_if:action,quote', 'nullable', 'numeric', 'min:0.01', 'max:99999999.99'],
            'currency' => ['required_if:action,quote', 'in:AMD,USD,EUR,RUB,GBP,AED'],
            'quote_terms' => ['required_if:action,quote', 'string', 'max:6000'],
            'quote_expires_at' => ['required_if:action,quote', 'date', 'after:now'],
            'confirmation_reference' => ['required_if:action,confirm', 'string', 'max:190'],
            'component_references' => ['sometimes', 'array', 'max:20'],
            'component_references.*' => ['required', 'string', 'max:190'],
            'component_index' => ['required_if:action,component', 'integer', 'min:0', 'max:19'],
            'component_status' => ['required_if:action,component', 'in:pending,confirmed,failed,cancelled'],
            'supplier_reference' => ['required_if:component_status,confirmed', 'nullable', 'string', 'max:190'],
            'components' => ['sometimes', 'array', 'max:20'],
            'components.*' => ['array:type,title,cost,sell_price,currency,status,supplier_reference'],
            'components.*.type' => ['required', 'in:flight,accommodation,transport,transfer,tour,activity,custom'],
            'components.*.title' => ['required', 'string', 'max:190'],
            'components.*.cost' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'components.*.sell_price' => ['required', 'numeric', 'min:0', 'max:99999999'],
            'components.*.currency' => ['required', 'in:AMD,USD,EUR,RUB,GBP,AED'],
            'components.*.status' => ['required', 'in:pending,confirmed,cancelled'],
            'components.*.supplier_reference' => ['nullable', 'string', 'max:190'],
        ]);

        return DB::transaction(function () use ($request, $id, $data) {
            $order = Booking::lockForUpdate()->findOrFail($id);
            abort_if($order->version !== $data['version'], 409, 'Մեկ այլ օգտվող փոփոխել է հայտը։ Թարմացրեք այն։');
            $allowed = [
                'review' => ['new'], 'quote' => ['new', 'reviewing', 'quoted'],
                'confirm' => ['accepted'], 'accept_guest' => ['quoted'], 'complete' => ['confirmed'],
                'cancel' => ['new', 'reviewing', 'quoted', 'accepted', 'confirmed', 'cancellation_requested'],
                'note' => ['new', 'reviewing', 'quoted', 'accepted', 'confirmed', 'completed', 'cancelled', 'cancellation_requested'],
                'component' => ['accepted', 'cancellation_requested'],
            ];
            abort_unless(in_array($order->status, $allowed[$data['action']]), 409, 'Կարգավիճակի այս փոփոխությունն անհասանելի է։');
            $updates = ['version' => $order->version + 1];
            $status = ['review' => 'reviewing', 'quote' => 'quoted', 'accept_guest' => 'accepted', 'confirm' => 'confirmed', 'complete' => 'completed', 'cancel' => 'cancelled', 'note' => $order->status, 'component' => $order->status][$data['action']];
            if ($data['action'] === 'component') {
                $components = $order->components ?? [];
                abort_unless(isset($components[$data['component_index']]) && filled($data['note'] ?? null), 422, 'Ընտրեք բաղադրիչը և նկարագրեք փաստացի փոփոխությունը։');
                $components[$data['component_index']]['status'] = $data['component_status'];
                $components[$data['component_index']]['supplier_reference'] = $data['supplier_reference'] ?? null;
                $updates['components'] = $components;
            }
            if ($data['action'] === 'quote') {
                $components = $data['components'] ?? [];
                if ($components && (collect($components)->contains(fn ($c) => $c['currency'] !== $data['currency']) || abs(collect($components)->sum('sell_price') - (float) $data['total_price']) > 0.009)) {
                    throw ValidationException::withMessages(['components' => ['Բաղադրիչների արժույթը և գումարը պետք է համապատասխանեն առաջարկին։']]);
                }
                $updates += array_intersect_key($data, array_flip(['total_price', 'currency', 'quote_terms', 'quote_expires_at']));
                $updates['components'] = array_map(fn ($component) => [...$component, 'status' => 'pending', 'supplier_reference' => null], $components);
            }
            if ($data['action'] === 'confirm') {
                abort_if(collect($order->components ?? [])->contains(fn ($component) => in_array($component['status'], ['failed', 'cancelled'])), 422, 'Նախ լուծեք փաթեթի չհաստատված բաղադրիչների խնդիրները։');
                $updates['confirmation_reference'] = $data['confirmation_reference'];
                $references = $data['component_references'] ?? [];
                if (count($references) !== count($order->components ?? [])) {
                    throw ValidationException::withMessages(['component_references' => ['Նշեք յուրաքանչյուր բաղադրիչի իրական հաստատման համարը։']]);
                }
                $updates['components'] = array_map(fn ($c, $i) => [...$c, 'status' => 'confirmed', 'supplier_reference' => $references[$i]], $order->components ?? [], array_keys($order->components ?? []));
            }
            if ($data['action'] === 'accept_guest') {
                abort_unless(! $order->user_id && $order->quote_expires_at?->isFuture() && filled($data['note'] ?? null), 422, 'Գրանցեք հյուրի համաձայնության աղբյուրը և համոզվեք, որ առաջարկի ժամկետը չի ավարտվել։');
            }
            if ($data['action'] === 'cancel') {
                if (in_array($order->status, ['accepted', 'confirmed', 'cancellation_requested']) && empty($data['note'])) {
                    throw ValidationException::withMessages(['note' => ['Նշեք մատակարարի չեղարկման և վերադարձի փաստացի արդյունքը։']]);
                }
                $updates['components'] = array_map(fn ($c) => [...$c, 'status' => 'cancelled'], $order->components ?? []);
            }
            if ($data['action'] === 'note' && empty($data['note'])) {
                throw ValidationException::withMessages(['note' => ['Գրեք հաղորդագրությունը։']]);
            }
            $order->update([...$updates, 'status' => $status]);
            $snapshot = $data['action'] === 'quote' ? $order->only(['total_price', 'currency', 'quote_terms', 'quote_expires_at', 'version']) : null;
            $order->events()->create(['actor_id' => $request->user()->id, 'status' => $status, 'message' => $data['note'] ?? null, 'internal' => $data['action'] === 'note' && ($data['internal'] ?? false), 'snapshot' => $snapshot]);

            return $order->load(['events' => fn ($q) => $q->oldest('id')]);
        });
    }

    public function reports()
    {
        return [
            'statuses' => Booking::selectRaw('status, count(*) as count')->groupBy('status')->get(),
            'currencies' => Booking::whereIn('status', ['confirmed', 'completed'])->selectRaw('currency, count(*) as orders, sum(total_price) as confirmed_value')->groupBy('currency')->get(),
            'customers' => User::where('role', 'customer')->count(),
            'payments_enabled' => false,
        ];
    }
}
