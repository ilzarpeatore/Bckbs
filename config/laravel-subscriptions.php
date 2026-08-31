<?php

return [
    'tables' => [
        'plans' => 'plans',
        'features' => 'plan_features',
        'subscriptions' => 'plan_subscriptions',
        'subscription_usage' => 'plan_subscription_usage',
    ],
    'models' => [
        'plan' => \App\Models\Plan::class,
        'feature' => \App\Models\PlanFeature::class,
        'subscription' => \App\Models\PlanSubscription::class,
        'subscription_usage' => \App\Models\PlanSubscriptionUsage::class,
    ],
];
