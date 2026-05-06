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

        // Load user
        $user = DB::selectOne(
            "SELECT u.*, bu.role AS business_role, bu.business_id AS token_business_id
             FROM users u
             LEFT JOIN business_users bu ON bu.user_id = u.id AND bu.active = 1
             WHERE u.id = ? AND u.active = 1
             LIMIT 1",
            [$tokenRow->tokenable_id]
        );

        if (!$user) {
            return $this->unauthorized('User not found or inactive');
        }

        // Resolve business_id: from token, then X-Business-ID header, then first available
        $businessId = (int)($tokenRow->business_id ?? 0) ?: null;

        if (!$businessId) {
            $header     = $request->getHeaderLine('X-Business-ID');
            $businessId = $header ? (int)$header : null;
        }

        // If business_id present, verify user belongs to it
        if ($businessId) {
            $member = DB::selectOne(
                "SELECT id, role FROM business_users
                 WHERE business_id = ? AND user_id = ? AND active = 1 LIMIT 1",
                [$businessId, $user->id]
            );
            if (!$member) {
                return $this->unauthorized('Access denied to this business');
            }
            $user->business_role = $member->role;
        }

        Auth::setUser($user);
        Auth::setBusinessId($businessId);

        // Update last_used_at async (fire and forget style)
        Auth::updateTokenLastUsed($token);

        // Lazy: sync overdue invoice statuses once per calendar day per business
        if ($businessId) {
            $today  = date('Y-m-d');
            $synced = DB::selectOne(
                "SELECT value FROM settings WHERE business_id = ? AND `key` = 'overdue_synced_date' LIMIT 1",
                [$businessId]
            );
            if (!$synced || $synced->value !== $today) {
                DB::statement(
                    "UPDATE invoices SET status = 'overdue'
                     WHERE business_id = ? AND status IN ('sent','partial') AND due_date < CURDATE()",
                    [$businessId]
                );
                DB::statement(
                    "INSERT INTO settings (business_id, `key`, value)
                     VALUES (?, 'overdue_synced_date', ?)
                     ON DUPLICATE KEY UPDATE value = VALUES(value)",
                    [$businessId, $today]
                );
            }
        }

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
