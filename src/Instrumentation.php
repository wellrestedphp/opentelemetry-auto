<?php

declare(strict_types=1);

namespace WellRESTed\Instrumentation;

use OpenTelemetry\API\Globals;

use OpenTelemetry\API\Instrumentation\CachedInstrumentation;
use OpenTelemetry\API\Trace\Span;
use OpenTelemetry\API\Trace\SpanInterface;
use OpenTelemetry\API\Trace\SpanKind;
use OpenTelemetry\API\Trace\StatusCode;
use OpenTelemetry\Context\Context;
use OpenTelemetry\SemConv\TraceAttributes;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Throwable;
use WellRESTed\Routing\Route\Route;

use function OpenTelemetry\Instrumentation\hook;

class Instrumentation
{
    public const NAMME = 'wellrested';

    public static function register(): void
    {
        $instrumentation = new CachedInstrumentation('org.wellrested.instrumentation', 'https://opentelemetry.io/schemas/1.25.0');

        hook(
            \WellRESTed\Server::class,
            'handle',
            pre: static function (
                \WellRESTed\Server $server,
                array $params,
                string $class,
                string $function,
                ?string $filename,
                ?int $lineno
            ) use ($instrumentation) {

                /** @var ServerRequestInterface $request */
                $request = ($params[0] instanceof ServerRequestInterface) ? $params[0] : null;

                $builder = $instrumentation->tracer()
                    ->spanBuilder(sprintf('%s', $request?->getMethod() ?? 'unknown'))
                    ->setSpanKind(SpanKind::KIND_SERVER)
                    ->setAttribute(TraceAttributes::CODE_FUNCTION, $function)
                    ->setAttribute(TraceAttributes::CODE_NAMESPACE, $class)
                    ->setAttribute(TraceAttributes::CODE_FILEPATH, $filename)
                    ->setAttribute(TraceAttributes::CODE_LINENO, $lineno);

                $parent = Context::getCurrent();

                if ($request) {
                    $parent = Globals::propagator()->extract($request->getHeaders());
                    $span = $builder
                        ->setParent($parent)
                        ->setAttribute(TraceAttributes::URL_FULL, $request->getUri()->__toString())
                        ->setAttribute(TraceAttributes::HTTP_REQUEST_METHOD, $request->getMethod())
                        ->setAttribute(TraceAttributes::HTTP_REQUEST_BODY_SIZE, $request->getHeaderLine('Content-Length'))
                        ->setAttribute(TraceAttributes::USER_AGENT_ORIGINAL, $request->getHeaderLine('User-Agent'))
                        ->setAttribute(TraceAttributes::SERVER_ADDRESS, $request->getUri()->getHost())
                        ->setAttribute(TraceAttributes::SERVER_PORT, $request->getUri()->getPort())
                        ->setAttribute(TraceAttributes::URL_SCHEME, $request->getUri()->getScheme())
                        ->setAttribute(TraceAttributes::URL_PATH, $request->getUri()->getPath())
                        ->startSpan();
                    $request = $request->withAttribute(SpanInterface::class, $span);
                } else {
                    $span = $builder->startSpan();
                }
                Context::storage()->attach($span->storeInContext($parent));

                return [$request];
            },
            post: static function (
                \WellRESTed\Server $server,
                array $params,
                ?ResponseInterface $response,
                ?Throwable $exception
            ): ResponseInterface {

                $scope = Context::storage()->scope();
                if (!$scope) {
                    return $response;
                }
                $scope->detach();
                $span = Span::fromContext($scope->context());
                if ($exception) {
                    $span->recordException($exception, [TraceAttributes::EXCEPTION_ESCAPED => true]);
                    $span->setStatus(StatusCode::STATUS_ERROR, $exception->getMessage());
                }
                if ($response) {
                    if ($response->getStatusCode() >= 400) {
                        $span->setStatus(StatusCode::STATUS_ERROR);
                    }
                    $span->setAttribute(TraceAttributes::HTTP_RESPONSE_STATUS_CODE, $response->getStatusCode());
                    $span->setAttribute(TraceAttributes::NETWORK_PROTOCOL_VERSION, $response->getProtocolVersion());
                    $span->setAttribute(TraceAttributes::HTTP_RESPONSE_BODY_SIZE, $response->getHeaderLine('Content-Length'));
                }
                $span->end();

                return $response;
            }
        );

        hook(
            \WellRESTed\Routing\Router::class,
            'dispatch',
            pre: null,
            post: static function (
                \WellRESTed\Routing\Router $router,
                array $params,
                ResponseInterface $response
            ) {
                $route = $params[0];
                $request = $params[1];
                if (!$route instanceof Route) {
                    return;
                }
                if (!$request instanceof ServerRequestInterface) {
                    return;
                }

                $span = $request->getAttribute(SpanInterface::class);
                if (!$span instanceof SpanInterface) {
                    return;
                }

                $span->setAttribute(TraceAttributes::HTTP_ROUTE, $route->getTarget());
                $span->updateName(sprintf('%s %s', $request->getMethod(), $route->getTarget()));
            }
        );
    }
}
