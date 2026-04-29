<?php

namespace App\Controllers;

use App\Base\Task;
use App\Core\Auth;
use App\Core\RequestHolder;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class AuthController
{
    public function login(Request $request, Response $response): Response
    {
        $result = Task::run('Auth.login', RequestHolder::all());
        return $this->json($response, $result);
    }

    public function register(Request $request, Response $response): Response
    {
        $result = Task::run('Auth.register', RequestHolder::all());
        return $this->json($response, $result);
    }

    public function logout(Request $request, Response $response): Response
    {
        $result = Task::run('Auth.logout', []);
        return $this->json($response, $result);
    }

    public function switchBusiness(Request $request, Response $response): Response
    {
        $result = Task::run('Auth.switchBusiness', RequestHolder::all());
        return $this->json($response, $result);
    }

    public function me(Request $request, Response $response): Response
    {
        $user       = Auth::user();
        $businessId = Auth::businessId();

        return $this->json($response, [
            'success' => true,
            'data'    => [
                'user'        => $user,
                'business_id' => $businessId,
            ],
        ]);
    }

    private function json(Response $response, array $data, int $status = 200): Response
    {
        $response->getBody()->write(json_encode($data));
        return $response
            ->withHeader('Content-Type', 'application/json')
            ->withStatus($status);
    }
}
