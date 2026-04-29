<?php

namespace App\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use App\Core\RequestHolder;

class RequestMiddleware
{
    public function __invoke(
        ServerRequestInterface  $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        RequestHolder::set($request);
        return $handler->handle($request);
    }
}
