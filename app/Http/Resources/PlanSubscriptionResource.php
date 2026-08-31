<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class PlanSubscriptionResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $subscriber = $this->subscriber;
        $plan = $this->plan;

        return [
            'id' => $this->id,
            'subscriber_type' => $this->subscriber_type,
            'subscriber_id' => $this->subscriber_id,
            'subscriber' => $subscriber
                ? [
                    'id' => $subscriber->id,
                    'name' => $subscriber->display_name ?? trim($subscriber->first_name . ' ' . $subscriber->last_name),
                    'email' => $subscriber->email,
                ]
                : null,
            'subscriber_name' => $subscriber
                ? ($subscriber->display_name ?? $subscriber->first_name . ' ' . $subscriber->last_name)
                : null,
            'plan_id' => $this->plan_id,
            'plan' => $plan
                ? [
                    'id' => $plan->id,
                    'name' => $plan->name,
                    'price' => (float) $plan->price,
                    'currency' => $plan->currency,
                ]
                : null,
            'plan_name' => $plan?->name,
            'plan_price' => $plan?->price,
            'price' => $this->total_amount,
            'currency' => $plan?->currency ?: 'EUR',
            'name' => $this->name,
            'slug' => $this->slug,
            'total_amount' => $this->total_amount,
            'amount_paid_cents' => $this->amount_paid_cents,
            'payment_method' => $this->payment_method,
            'payment_notes' => $this->payment_notes,
            'payment_status' => $this->payment_status,
            'trial_ends_at' => $this->trial_ends_at?->toISOString(),
            'starts_at' => $this->starts_at?->toISOString(),
            'ends_at' => $this->ends_at?->toISOString(),
            'canceled_at' => $this->canceled_at?->toISOString(),
            'created_at' => $this->created_at?->toISOString(),
            'updated_at' => $this->updated_at?->toISOString(),
        ];
    }
}
