<?php

use App\Controllers\AuthController;
use App\Controllers\SqlController;
use App\Controllers\TaskController;
use App\Controllers\EntityController;
use App\Middleware\AuthMiddleware;

// ── Guest routes ───────────────────────────────────────────────────────────────

$app->post('/login',    [AuthController::class, 'login']);
$app->post('/register', [AuthController::class, 'register']);

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
