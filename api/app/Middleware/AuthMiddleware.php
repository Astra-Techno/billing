<?php

namespace App\Middleware;

use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Server\RequestHandlerInterface;
use App\Core\Auth;
use App\Core\RequestHolder;
use App\Core\DB;

class AuthMiddleware
{
    public function __invoke(
        ServerRequestInterface  $request,
        RequestHandlerInterface $handler
    ): ResponseInterface {
        $token = RequestHolder::bearerToken();

        if (!$token) {
            return $this->unauthorized('No token provided');
        }

        $tokenRow = Auth::validateToken($token);

        if (!$tokenRow) {
            return $this->unauthorized('Invalid or expired token');
        }

        // Resolve business_id: from token, then X-Business-ID header, then first available
        $businessId = (int)($tokenRow->business_id ?? 0) ?: null;

        if (!$businessId) {
            $header     = $request->getHeaderLine('X-Business-ID');
            $businessId = $header ? (int)$header : null;
        }

        // Single query when business is known (typical app requests)
        if ($businessId) {
            $user = DB::selectOne(
                "SELECT u.*, bu.role AS business_role, bu.business_id AS token_business_id
                 FROM users u
                 INNER JOIN business_users bu ON bu.user_id = u.id AND bu.business_id = ? AND bu.active = 1
                 WHERE u.id = ? AND u.active = 1
                 LIMIT 1",
                [$businessId, $tokenRow->tokenable_id]
            );
        } else {
            $user = DB::selectOne(
                "SELECT u.*, bu.role AS business_role, bu.business_id AS token_business_id
                 FROM users u
                 LEFT JOIN business_users bu ON bu.user_id = u.id AND bu.active = 1
                 WHERE u.id = ? AND u.active = 1
                 LIMIT 1",
                [$tokenRow->tokenable_id]
            );
        }

        if (!$user) {
            return $this->unauthorized('User not found or inactive');
        }

        if ($businessId) {
            $user->business_role = $user->business_role ?? null;
        }

        Auth::setUser($user);
        Auth::setBusinessId($businessId);

        // Throttle token write — skip if used within last 5 minutes
        Auth::updateTokenLastUsed($token, $tokenRow->last_used_at ?? null);

        return $handler->handle($request);
    }

    private function unauthorized(string $message): ResponseInterface
    {
        $origin   = $_ENV['FRONTEND_URL'] ?? '*';
        $response = new \Slim\Psr7\Response();
        $response->getBody()->write(json_encode([
            'success' => false,
            'message' => $message,
        ]));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withHeader('Access-Control-Allow-Origin',      $origin)
            ->withHeader('Access-Control-Allow-Headers',     'Content-Type, Authorization, X-Requested-With, X-Business-ID')
            ->withHeader('Access-Control-Allow-Methods',     'GET, POST, PUT, PATCH, DELETE, OPTIONS')
            ->withHeader('Access-Control-Allow-Credentials', 'true')
            ->withStatus(401);
    }
}
