<?php

namespace App\Controllers;

use App\Base\Sql;
use App\Core\RequestHolder;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class SqlController
{
    public function list(Request $request, Response $response, array $args): Response
    {
        $name  = $this->withMethod($args['name'], 'list');
        $input = array_merge(RequestHolder::all(), ['select_type' => 'list']);
        $limit = (int)($input['limit'] ?? 0);
        if ($limit <= 0) {
            $input['limit'] = 50;
        } elseif ($limit > 1000) {
            $input['limit'] = 1000;
        }
        if (!isset($input['page']) || (int)$input['page'] < 1) {
            $input['page'] = 1;
        }
        $sql  = (new Sql())->load($name, $input);
        $data = $sql->assocList();

        // Also fetch total count (same filters, no limit) — skip if SQL has no 'total' select type
        try {
            $totalInput = array_merge(RequestHolder::all(), ['select_type' => 'total']);
            $totalSql   = (new Sql())->load($name, $totalInput);
            $totalRow   = $totalSql->assoc();
            $total      = (int)($totalRow['total'] ?? count($data));
        } catch (\Throwable $e) {
            $total = count($data);
        }

        return $this->json($response, [
            'success' => true,
            'data'    => $data,
            'total'   => $total,
            'limit'   => (int)$input['limit'],
            'page'    => (int)$input['page'],
        ]);
    }

    public function all(Request $request, Response $response, array $args): Response
    {
        $name  = $this->withMethod($args['name'], 'all');
        $input = RequestHolder::all();
        $sql   = (new Sql())->load($name, $input);
        return $this->json($response, ['success' => true, 'data' => $sql->assocList()]);
    }

    public function item(Request $request, Response $response, array $args): Response
    {
        $name  = $this->withMethod($args['name'], 'entity');
        $input = RequestHolder::all();
        $sql   = (new Sql())->load($name, $input);
        return $this->json($response, ['success' => true, 'data' => $sql->assoc()]);
    }

    public function count(Request $request, Response $response, array $args): Response
    {
        $name  = $this->withMethod($args['name'], 'list');
        $input = array_merge(RequestHolder::all(), ['select_type' => 'total']);
        $sql   = (new Sql())->load($name, $input);
        return $this->json($response, ['success' => true, 'total' => (int)$sql->result()]);
    }

    public function options(Request $request, Response $response, array $args): Response
    {
        $name  = $this->withMethod($args['name'], 'options');
        $input = array_merge(RequestHolder::all(), ['select_type' => 'options']);
        $sql   = (new Sql())->load($name, $input);
        return $this->json($response, ['success' => true, 'data' => $sql->assocList()]);
    }

    public function groupedList(Request $request, Response $response, array $args): Response
    {
        $name        = $this->withMethod($args['name'], 'list');
        $input       = array_merge(RequestHolder::all(), ['select_type' => 'list']);
        $groupColumn = $input['group_by'] ?? null;
        $sql         = (new Sql())->load($name, $input);
        return $this->json($response, ['success' => true, 'data' => $sql->assocList($groupColumn)]);
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    /** Normalize URL separator (:) to Factory separator (.) and append default method if missing. */
    private function withMethod(string $name, string $default): string
    {
        $name = str_replace(':', '.', $name); // URL uses : but Factory expects .
        return str_contains($name, '.') ? $name : $name . '.' . $default;
    }

    private function json(Response $response, mixed $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}
