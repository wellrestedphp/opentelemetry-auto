<?php

declare(strict_types=1);

namespace WellRESTed\Instrumentation;

use ArrayObject;
use OpenTelemetry\API\Instrumentation\Configurator;
use OpenTelemetry\Context\ScopeInterface;
use OpenTelemetry\SDK\Trace\SpanExporter\InMemoryExporter;
use OpenTelemetry\SDK\Trace\SpanProcessor\SimpleSpanProcessor;
use OpenTelemetry\SDK\Trace\TracerProvider;
use PHPUnit\Framework\TestCase;
use WellRESTed\Message\Response;
use WellRESTed\Message\ServerRequest;
use WellRESTed\Server;

class InstrumentationTest extends TestCase
{
    private ScopeInterface $scope;
    private ArrayObject $storage;

    public static function setUpBeforeClass(): void
    {
        Instrumentation::register();
    }

    public function setUp(): void
    {
        $this->storage = new ArrayObject();
        $traceProvider = new TracerProvider(
            new SimpleSpanProcessor(
                new InMemoryExporter($this->storage)
            )
        );

        $this->scope = Configurator::create()
            ->withTracerProvider($traceProvider)
            ->activate();
    }

    public function tearDown(): void
    {
        $this->scope->detach();
    }

    /** @dataProvider routeProvider */
    public function test_router_updates_root_span_name(
        string $method,
        string $target,
        string $expected
    ) {
        $server = new Server();
        $router = $server->createRouter();
        $router->register('GET', '/foo', new Response(200));
        $router->register('GET', '/bar/{id}', new Response(200));
        $server->add($router);

        $request = new ServerRequest($method, $target);
        $server->handle($request);

        $this->assertGreaterThanOrEqual(1, count($this->storage));
        $span = $this->storage->offsetGet(0);
        $this->assertSame($expected, $span->getName());
    }

    public function routeProvider(): array
    {
        return [
            'static route' => ['GET', '/foo', 'GET /foo'],
            'pattern route' => ['PUT', '/bar/123', 'PUT /bar/{id}'],
        ];
    }
}
