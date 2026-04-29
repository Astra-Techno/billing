<?php

namespace App\Controllers;

use App\Base\Entity;
use App\Core\RequestHolder;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class EntityController
{
    /**
     * GET /api/entity/{name}[/{param}]
     *
     * Loads App\Sql\{Name}::entity($input) and returns the row.
     */
    public function get(Request $request, Response $response, array $args): Response
    {
        $name  = $args['name'];
        $input = RequestHolder::all();

        if (!empty($args['param']))
            $input['id'] = $args['param'];

        $entity = Entity::load($name, $input);

        return $this->json($response, [
            'success' => true,
            'data'    => $entity->toArray(),
        ]);
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
