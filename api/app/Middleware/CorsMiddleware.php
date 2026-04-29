<?php

namespace App\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;

class CorsMiddleware
{
    public function __invoke(
        ServerRequestInterface  $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $origin  = $_ENV['FRONTEND_URL'] ?? '*';

        // Handle pre-flight OPTIONS
        if ($request->getMethod() === 'OPTIONS') {
            $response = new \Slim\Psr7\Response();
            return $response
                ->withHeader('Access-Control-Allow-Origin',  $origin)
                ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-Business-ID')
                ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
                ->withHeader('Access-Control-Allow-Credentials', 'true')
                ->withStatus(200);
        }

        $response = $handler->handle($request);

        return $response
            ->withHeader('Access-Control-Allow-Origin',  $origin)
            ->withHeader('Access-Control-Allow-Headers', 'Content-Type, Authorization, X-Requested-With, X-Business-ID')
            ->withHeader('Access-Control-Allow-Methods', 'GET, POST, PUT, PATCH, DELETE, OPTIONS')
            ->withHeader('Access-Control-Allow-Credentials', 'true');
    }
}
