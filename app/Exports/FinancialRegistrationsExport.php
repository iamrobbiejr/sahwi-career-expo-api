<?php

namespace App\Exports;

use App\Models\EventRegistration;
use DateTimeInterface;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithMapping;

class FinancialRegistrationsExport implements FromCollection, WithHeadings, WithMapping
{
    public function __construct(
        private int                $eventId,
        private ?string            $search = null,
        private ?DateTimeInterface $from = null,
        private ?DateTimeInterface $to = null,
        private ?string            $gateway = null,
        private ?string            $method = null,
    )
    {
    }

    public function collection()
    {
        $query = EventRegistration::query()
            ->with(['user:id,name,email', 'paymentItem.payment'])
            ->where('event_id', $this->eventId)
            ->whereHas('paymentItem.payment', function ($q) {
                $q->where('status', 'completed');
                if ($this->from) {
                    $q->whereDate('paid_at', '>=', $this->from);
                }
                if ($this->to) {
                    $q->whereDate('paid_at', '<=', $this->to);
                }
                if ($this->gateway) {
                    $q->where('gateway_name', $this->gateway);
                }
                if ($this->method) {
                    $q->where('payment_method', $this->method);
                }
            });

        if ($this->search) {
            $term = $this->search;
            $query->where(function ($sub) use ($term) {
                $sub->where('attendee_name', 'ilike', "%$term%")
                    ->orWhere('attendee_email', 'ilike', "%$term%")
                    ->orWhere('ticket_number', 'ilike', "%$term%")
                    ->orWhereHas('user', function ($u) use ($term) {
                        $u->where('name', 'ilike', "%$term%")
                            ->orWhere('email', 'ilike', "%$term%");
                    });
            });
        }

        return $query->orderByDesc('registered_at')->get();
    }

    public function headings(): array
    {
        return [
            'Registration ID',
            'Ticket Number',
            'Attendee Name',
            'Attendee Email',
            'Status',
            'Paid Amount',
            'Currency',
            'Payment Method',
            'Gateway',
            'Paid At',
        ];
    }

    public function map($r): array
    {
        $payment = $r->paymentItem?->payment;
        $item = $r->paymentItem;
        return [
            $r->id,
            $r->ticket_number,
            $r->attendee_name,
            $r->attendee_email,
            $r->status,
            $item ? ($item->total_amount_cents / 100) : 0,
            $payment?->currency,
            $payment?->payment_method,
            $payment?->gateway_name,
            optional($payment?->paid_at)->toDateTimeString(),
        ];
    }
}
