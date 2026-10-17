<?php

namespace App\Middleware;

use App\Core\Auth;
use App\Core\DB;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

final class CounterDeviceMiddleware
{
    public function __invoke(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        Auth::setDeviceContext(null, null, null);
        if (($_ENV['DESKTOP_MODE'] ?? '') !== 'true' || ($_ENV['DESKTOP_NETWORK_MODE'] ?? 'SinglePc') !== 'MainPc') {
            return $handler->handle($request);
        }

        $ip = $request->getServerParams()['REMOTE_ADDR'] ?? '';
        if (in_array($ip, ['127.0.0.1', '::1', '::ffff:127.0.0.1'], true)) return $handler->handle($request);

        $uuid = strtolower(trim($request->getHeaderLine('X-AI-Device-ID')));
        $token = trim($request->getHeaderLine('X-AI-Device-Session'));
        if (!$uuid || !$token) return $this->deny('This Counter PC is not connected. Run AI Billing Counter Setup again.', 401);

        $device = DB::selectOne(
            "SELECT d.id,d.business_id,d.counter_id,c.location_id,s.id session_id,s.last_seen_at
             FROM device_sessions s
             INNER JOIN business_devices d ON d.id=s.device_id AND d.status='active'
             INNER JOIN billing_counters c ON c.id=d.counter_id AND c.business_id=d.business_id AND c.active=1
             WHERE d.device_uuid=? AND s.token_hash=? AND s.expires_at>NOW() LIMIT 1",
            [$uuid, hash('sha256', $token)]
        );
        if (!$device) return $this->deny('This Counter PC session expired. Close and reopen AI Billing Counter.', 401);
        if ((int)$device->business_id !== (int)Auth::businessId()) return $this->deny('This user account does not belong to the paired shop.', 403);

        Auth::setDeviceContext((int)$device->id, (int)$device->counter_id, $device->location_id ? (int)$device->location_id : null);
        if (!$device->last_seen_at || strtotime($device->last_seen_at) < time() - 300) {
            DB::statement('UPDATE device_sessions SET last_seen_at=NOW() WHERE id=?', [$device->session_id]);
            DB::statement('UPDATE business_devices SET last_seen_at=NOW() WHERE id=?', [$device->id]);
        }
        return $handler->handle($request);
    }

    private function deny(string $message, int $status): ResponseInterface
    {
        $response = new \Slim\Psr7\Response();
        $response->getBody()->write(json_encode(['success'=>false, 'message'=>$message]));
        return $response->withHeader('Content-Type', 'application/json')->withStatus($status);
    }
}
