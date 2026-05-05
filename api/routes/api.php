<?php

use App\Controllers\AuthController;
use App\Controllers\SqlController;
use App\Controllers\TaskController;
use App\Controllers\EntityController;
use App\Middleware\AuthMiddleware;

// ── Guest routes ───────────────────────────────────────────────────────────────

$app->post('/login',    [AuthController::class, 'login']);
$app->post('/register', [AuthController::class, 'register']);

// ── Public digital business card ───────────────────────────────────────────────
$app->get('/shop/{slug}', function ($request, $response, $args) {
    $slug = preg_replace('/[^a-z0-9\-]/', '', strtolower($args['slug'] ?? ''));
    if (!$slug) {
        $response->getBody()->write(json_encode(['success' => false, 'message' => 'Invalid slug']));
        return $response->withHeader('Content-Type', 'application/json')->withStatus(400);
    }

    $db   = \App\Core\DB::connection();
    $stmt = $db->prepare('
        SELECT b.id, b.name, b.slug, b.logo, b.mobile, b.phone, b.email, b.website,
               b.address_line1, b.address_line2, b.city, b.pincode,
               b.upi_id, b.gstin, b.business_type, b.is_gst_registered,
               s.name AS state_name
        FROM businesses b
        LEFT JOIN indian_states s ON s.id = b.state_id
        WHERE b.slug = ? AND b.active = 1
        LIMIT 1
    ');
    $stmt->execute([$slug]);
    $data = $stmt->fetch(\PDO::FETCH_ASSOC);

    $payload = $data
        ? ['success' => true,  'data'    => $data]
        : ['success' => false, 'message' => 'Business not found'];

    $response->getBody()->write(json_encode($payload));
    return $response->withHeader('Content-Type', 'application/json')
                    ->withStatus($data ? 200 : 404);
});

$app->get( '/guest-task/{name}/{method}[/{param:.*}]', [TaskController::class, 'guest']);
$app->post('/guest-task/{name}/{method}[/{param:.*}]', [TaskController::class, 'guest']);

// Public reference data (no auth needed)
$app->get('/all/{name:IndianState|Plan}', [SqlController::class, 'all']);

// ── Authenticated routes ───────────────────────────────────────────────────────

$app->group('', function ($group) {

    $group->post('/logout',          [AuthController::class, 'logout']);
    $group->post('/switch-business', [AuthController::class, 'switchBusiness']);

    $urlMatch = '{name:[a-zA-Z0-9/]+(?::[a-zA-Z0-9]+)?}';
    $group->get( '/list/'       . $urlMatch, [SqlController::class, 'list']);
    $group->post('/list/'       . $urlMatch, [SqlController::class, 'list']);
    $group->get( '/all/'        . $urlMatch, [SqlController::class, 'all']);
    $group->post('/all/'        . $urlMatch, [SqlController::class, 'all']);
    $group->get( '/item/'       . $urlMatch, [SqlController::class, 'item']);
    $group->post('/item/'       . $urlMatch, [SqlController::class, 'item']);
    $group->get( '/count/'      . $urlMatch, [SqlController::class, 'count']);
    $group->post('/count/'      . $urlMatch, [SqlController::class, 'count']);
    $group->get( '/options/'    . $urlMatch, [SqlController::class, 'options']);
    $group->post('/options/'    . $urlMatch, [SqlController::class, 'options']);
    $group->get( '/group-list/' . $urlMatch, [SqlController::class, 'groupedList']);
    $group->post('/group-list/' . $urlMatch, [SqlController::class, 'groupedList']);

    $group->get( '/task/{name}/{method}[/{param:.*}]', [TaskController::class, 'action']);
    $group->post('/task/{name}/{method}[/{param:.*}]', [TaskController::class, 'action']);

    $group->get( '/entity/{path:.*}', [EntityController::class, 'fetch']);
    $group->post('/entity/{path:.*}', [EntityController::class, 'fetch']);

})->add(new AuthMiddleware());
