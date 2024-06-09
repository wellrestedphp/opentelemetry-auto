<?php

declare(strict_types=1);

use OpenTelemetry\SDK\Sdk;
use WellRESTed\Instrumentation\Instrumentation;

if (class_exists(Sdk::class) && Sdk::isInstrumentationDisabled(Instrumentation::NAME)) {
    return;
}

if (!extension_loaded('opentelemetry')) {
    trigger_error('The opentelemetry extension must be loaded tin order to autoload the WellRESTed auto-instrumentation', E_USER_WARNING);
    return;
}

Instrumentation::register();
