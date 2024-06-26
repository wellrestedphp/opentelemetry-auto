WellRESTed Open Telemetry Auto Instrumentation
==============================================

Please read https://opentelemetry.io/docs/instrumentation/php/automatic/ for instructions on how to install and configure the extension and SDK.

## Overview

Auto-instrumentation hooks are registered via composer, and spans will automatically be created for:

- `Server::handle()`: Created the root span
- `Router::dispatch()`: Updates the root span name with the matched route

## Configuration

The extension can be disabled via [runtime configuration](https://opentelemetry.io/docs/instrumentation/php/sdk/#configuration):

```shell
OTEL_PHP_DISABLED_INSTRUMENTATIONS=wellrested
```
