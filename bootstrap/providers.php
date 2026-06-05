<?php

use App\Providers\AppServiceProvider;
use App\Providers\MailAllowlistProvider;
use App\Providers\ObservabilityServiceProvider;

return [
    AppServiceProvider::class,
    MailAllowlistProvider::class,
    ObservabilityServiceProvider::class,
];
