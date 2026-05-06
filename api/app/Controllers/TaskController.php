<?php

namespace App\Controllers;

use App\Base\Task;
use App\Core\RequestHolder;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class TaskController
{
    /**
     * POST /api/task/{name}/{method}[/{param}]
     *
     * Runs App\Task\{Name}::{method}($input)
     */
    public function action(Request $request, Response $response, array $args): Response
    {
        $name   = $args['name'];
        $method = $args['method'] ?? 'handle';
        $input  = RequestHolder::all();

        // Inject route param as 'id' if present
        if (!empty($args['param']))
            $input['id'] = $args['param'];

        $taskName = $name . '.' . $method;
        $result   = Task::run($taskName, $input);

        return $this->json($response, $result);
    }

    /**
     * POST /api/guest-task/{name}/{method}[/{param}]
     *
     * Same as run() but accessible without auth (guest tasks).
     */
    public function guest(Request $request, Response $response, array $args): Response
    {
        return $this->action($request, $response, $args);
    }

    // ── Private ───────────────────────────────────────────────────────────────

    private function json(Response $response, mixed $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}
