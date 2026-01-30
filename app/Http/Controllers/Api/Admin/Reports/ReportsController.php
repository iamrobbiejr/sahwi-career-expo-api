<?php

namespace App\Http\Controllers\Api\Admin\Reports;

use App\Exports\FinancialRegistrationsExport;
use App\Http\Controllers\Controller;
use App\Models\Event;
use App\Models\EventRegistration;
use App\Models\Payment;
use App\Models\PaymentItem;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\Request;
use Maatwebsite\Excel\Facades\Excel;

class ReportsController extends Controller
{
    /**
     * Financial analysis — all paid registrations for an event with search/pagination.
     */
    public function financial(Request $request)
    {
        $validated = $request->validate([
            'event_id' => ['required', 'integer', 'exists:events,id'],
            'q' => ['nullable', 'string'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'gateway' => ['nullable', 'string'],
            'method' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $eventId = (int)$validated['event_id'];
        $perPage = (int)($validated['per_page'] ?? 20);

        $query = EventRegistration::query()
            ->with(['user:id,name,email', 'event:id,name', 'paymentItem.payment'])
            ->where('event_id', $eventId)
            ->whereHas('paymentItem.payment', function ($q) use ($validated) {
                $q->where('status', 'completed');
                if (!empty($validated['from'])) {
                    $q->whereDate('paid_at', '>=', $validated['from']);
                }
                if (!empty($validated['to'])) {
                    $q->whereDate('paid_at', '<=', $validated['to']);
                }
                if (!empty($validated['gateway'])) {
                    $q->where('gateway_name', $validated['gateway']);
                }
                if (!empty($validated['method'])) {
                    $q->where('payment_method', $validated['method']);
                }
            });

        if (!empty($validated['q'])) {
            $q = $validated['q'];
            $query->where(function ($sub) use ($q) {
                $sub->where('attendee_name', 'ilike', "%$q%")
                    ->orWhere('attendee_email', 'ilike', "%$q%")
                    ->orWhere('ticket_number', 'ilike', "%$q%")
                    ->orWhereHas('user', function ($u) use ($q) {
                        $u->where('name', 'ilike', "%$q%")
                            ->orWhere('email', 'ilike', "%$q%");
                    });
            });
        }

        /** @var LengthAwarePaginator $paginator */
        $paginator = $query
            ->orderByDesc('registered_at')
            ->paginate($perPage);

        $data = $paginator->getCollection()->map(function (EventRegistration $r) {
            $payment = $r->paymentItem?->payment;
            $item = $r->paymentItem;
            return [
                'registration_id' => $r->id,
                'ticket_number' => $r->ticket_number,
                'attendee_name' => $r->attendee_name,
                'attendee_email' => $r->attendee_email,
                'status' => $r->status,
                'paid_amount' => $item ? ($item->total_amount_cents / 100) : 0,
                'currency' => $payment?->currency,
                'payment_method' => $payment?->payment_method,
                'gateway' => $payment?->gateway_name,
                'paid_at' => optional($payment?->paid_at)->toDateTimeString(),
            ];
        });

        // Compute overall totals matching the same filters
        $totalsQuery = PaymentItem::query()
            ->whereHas('payment', function ($q) use ($validated, $eventId) {
                $q->where('status', 'completed');
                if (!empty($validated['from'])) {
                    $q->whereDate('paid_at', '>=', $validated['from']);
                }
                if (!empty($validated['to'])) {
                    $q->whereDate('paid_at', '<=', $validated['to']);
                }
                if (!empty($validated['gateway'])) {
                    $q->where('gateway_name', $validated['gateway']);
                }
                if (!empty($validated['method'])) {
                    $q->where('payment_method', $validated['method']);
                }
            })
            ->whereHas('registration', function ($q) use ($validated, $eventId) {
                $q->where('event_id', $eventId);
                if (!empty($validated['q'])) {
                    $term = $validated['q'];
                    $q->where(function ($sub) use ($term) {
                        $sub->where('attendee_name', 'ilike', "%$term%")
                            ->orWhere('attendee_email', 'ilike', "%$term%")
                            ->orWhere('ticket_number', 'ilike', "%$term%")
                            ->orWhereHas('user', function ($u) use ($term) {
                                $u->where('name', 'ilike', "%$term%")
                                    ->orWhere('email', 'ilike', "%$term%");
                            });
                    });
                }
            });

        $totalCents = (int)$totalsQuery->selectRaw('COALESCE(SUM(amount_cents * quantity), 0) as total')->value('total');

        return response()->json([
            'data' => $data,
            'meta' => [
                'pagination' => [
                    'total' => $paginator->total(),
                    'per_page' => $paginator->perPage(),
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                ],
                'totals' => [
                    'total_paid' => $totalCents / 100,
                    'currency' => optional($data->first())['currency'] ?? null,
                ],
            ],
        ]);
    }

    /**
     * Export financial analysis to Excel
     */
    public function financialExport(Request $request)
    {
        $request->validate([
            'event_id' => ['required', 'integer', 'exists:events,id'],
            'q' => ['nullable', 'string'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
            'gateway' => ['nullable', 'string'],
            'method' => ['nullable', 'string'],
        ]);

        $event = Event::findOrFail((int)$request->integer('event_id'));
        $export = new FinancialRegistrationsExport(
            eventId: (int)$request->integer('event_id'),
            search: $request->string('q')->toString() ?: null,
            from: $request->date('from'),
            to: $request->date('to'),
            gateway: $request->string('gateway')->toString() ?: null,
            method: $request->string('method')->toString() ?: null,
        );

        $fileName = 'financial_registrations_' . str_replace(' ', '_', strtolower($event->name)) . '_' . now()->format('Ymd_His') . '.xlsx';
        return Excel::download($export, $fileName);
    }

    /**
     * Paid registrations count per gateway and/or payment method (JSON)
     */
    public function paymentsSummary(Request $request)
    {
        $validated = $request->validate([
            'event_id' => ['required', 'integer', 'exists:events,id'],
            'group_by' => ['nullable', 'in:gateway,method,gateway_method'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $eventId = (int)$validated['event_id'];
        $groupBy = $validated['group_by'] ?? 'gateway_method';

        $payments = Payment::query()
            ->selectRaw('gateway_name, payment_method, COUNT(*) as count, COALESCE(SUM(amount_cents),0) as total_cents')
            ->where('event_id', $eventId)
            ->where('status', 'completed');

        if (!empty($validated['from'])) {
            $payments->whereDate('paid_at', '>=', $validated['from']);
        }
        if (!empty($validated['to'])) {
            $payments->whereDate('paid_at', '<=', $validated['to']);
        }

        if ($groupBy === 'gateway') {
            $payments->groupBy('gateway_name');
        } elseif ($groupBy === 'method') {
            $payments->groupBy('payment_method');
        } else {
            $payments->groupBy('gateway_name', 'payment_method');
        }

        $rows = $payments->orderBy('gateway_name')->orderBy('payment_method')->get();

        $data = $rows->map(function ($row) use ($groupBy) {
            return [
                'gateway' => $groupBy === 'method' ? null : $row->gateway_name,
                'payment_method' => $groupBy === 'gateway' ? null : $row->payment_method,
                'count' => (int)$row->count,
                'total' => ((int)$row->total_cents) / 100,
            ];
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'group_by' => $groupBy,
                'currency' => optional(Event::find($eventId))->currency,
                'generated_at' => now()->toDateTimeString(),
            ],
        ]);
    }

    /**
     * Export payments summary PDF with date generated, event name and logo.
     */
    public function paymentsSummaryExport(Request $request)
    {
        $validated = $request->validate([
            'event_id' => ['required', 'integer', 'exists:events,id'],
            'group_by' => ['nullable', 'in:gateway,method,gateway_method'],
            'from' => ['nullable', 'date'],
            'to' => ['nullable', 'date'],
        ]);

        $event = Event::findOrFail((int)$validated['event_id']);
        $groupBy = $validated['group_by'] ?? 'gateway_method';

        $json = $this->paymentsSummary($request)->getData(true);

        $pdf = Pdf::loadView('reports.payments_summary', [
            'event' => $event,
            'group_by' => $groupBy,
            'rows' => $json['data'] ?? [],
            'generated_at' => now(),
        ])->setPaper('a4', 'portrait');

        $fileName = 'payments_summary_' . str_replace(' ', '_', strtolower($event->name)) . '_' . now()->format('Ymd_His') . '.pdf';
        return $pdf->download($fileName);
    }

    /**
     * All pending and cancelled registrations for an event with search/pagination.
     */
    public function pendingCancelled(Request $request)
    {
        $validated = $request->validate([
            'event_id' => ['required', 'integer', 'exists:events,id'],
            'q' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:200'],
        ]);

        $perPage = (int)($validated['per_page'] ?? 20);

        $query = EventRegistration::query()
            ->with(['user:id,name,email'])
            ->where('event_id', (int)$validated['event_id'])
            ->whereIn('status', ['pending', 'cancelled']);

        if (!empty($validated['q'])) {
            $q = $validated['q'];
            $query->where(function ($sub) use ($q) {
                $sub->where('attendee_name', 'ilike', "%$q%")
                    ->orWhere('attendee_email', 'ilike', "%$q%")
                    ->orWhere('ticket_number', 'ilike', "%$q%")
                    ->orWhereHas('user', function ($u) use ($q) {
                        $u->where('name', 'ilike', "%$q%")
                            ->orWhere('email', 'ilike', "%$q%");
                    });
            });
        }

        $paginator = $query->orderByDesc('registered_at')->paginate($perPage);

        $data = $paginator->getCollection()->map(function (EventRegistration $r) {
            return [
                'registration_id' => $r->id,
                'ticket_number' => $r->ticket_number,
                'attendee_name' => $r->attendee_name,
                'attendee_email' => $r->attendee_email,
                'status' => $r->status,
                'registered_at' => optional($r->registered_at)->toDateTimeString(),
                'cancelled_at' => optional($r->cancelled_at)->toDateTimeString(),
            ];
        });

        return response()->json([
            'data' => $data,
            'meta' => [
                'pagination' => [
                    'total' => $paginator->total(),
                    'per_page' => $paginator->perPage(),
                    'current_page' => $paginator->currentPage(),
                    'last_page' => $paginator->lastPage(),
                ],
            ],
        ]);
    }
}
