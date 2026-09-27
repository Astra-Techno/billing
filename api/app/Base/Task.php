<?php

namespace App\Base;

use App\Core\DB;
use App\Core\Validator;
use App\Core\Auth;
use App\Core\RequestHolder;

class Task extends ClassObject
{
    // ── Subclass can override ──────────────────────────────────────────────────
    protected array  $rules    = [];   // validation rules
    protected array  $messages = [];   // custom validation messages
    protected bool   $useTransaction = false;

    // ── Internal ───────────────────────────────────────────────────────────────
    protected array  $input    = [];
    protected ?object $user    = null;
    protected array  $output   = [];

    // =========================================================================
    // Execute (main entry point — called by TaskController)
    // =========================================================================

    /**
     * Load and run a Task method.
     *
     * Usage:
     *   Task::run('Auth.login', $input)
     *   Task::run('Invoice.create', $input)
     */
    final public static function run(string $name, array $input = []): array
    {
        [$className, $method] = Factory::findClass('App\\Task', $name);

        $method = $method ?? 'handle';

        if (!method_exists($className, $method))
            (new static())->raiseError("{$className}::{$method} - method not found!", 404);

        $instance         = new $className();
        $instance->input  = $input;
        $instance->user   = Auth::user();

        // Validate
        if (!empty($instance->rules)) {
            $validator = Validator::make($input, $instance->rules, $instance->messages);
            if ($validator->fails())
                $instance->raiseError($validator->firstError(), 422);
        }

        // Run (with optional transaction)
        if ($instance->useTransaction) {
            return DB::transaction(fn() => $instance->$method($input));
        }

        return $instance->$method($input);
    }

    // =========================================================================
    // Helpers available inside task methods
    // =========================================================================

    protected function input(string $key, mixed $default = null): mixed
    {
        return $this->input[$key] ?? $default;
    }

    protected function allInput(): array
    {
        return $this->input;
    }

    protected function user(): ?object
    {
        return $this->user;
    }

    protected function userId(): ?int
    {
        return $this->user ? (int)($this->user->id ?? null) : null;
    }

    protected function businessId(): ?int
    {
        return \App\Core\Auth::businessId();
    }

    protected function requireBusiness(): int
    {
        $id = $this->businessId();
        if (!$id) $this->fail('No active business context.', 403);
        return $id;
    }

    protected function userRole(): ?string
    {
        return $this->user?->business_role ?? null;
    }

    protected function requireRole(array $roles): void
    {
        if (!in_array($this->userRole(), $roles, true))
            $this->fail('Insufficient permissions.', 403);
    }

    /**
     * Check granular permission for accountant/staff roles.
     * Owner and admin always pass. For others, checks the permissions JSON.
     *
     * Usage:
     *   $this->requirePermission('invoices');           // view
     *   $this->requirePermission('invoices', 'create');
     *   $this->requirePermission('invoices', 'edit');
     *   $this->requirePermission('invoices', 'delete');
     */
    protected function requirePermission(string $module, string $action = 'view'): void
    {
        $role = $this->userRole();
        if (in_array($role, ['owner', 'admin'], true)) return;

        $perms = $this->userPermissions();

        // Check view (page-level) access
        if (!in_array($module, $perms, true))
            $this->fail('You do not have access to this module.', 403);

        // Check action-level access
        if ($action !== 'view') {
            $key = "{$module}.{$action}";
            if (!in_array($key, $perms, true))
                $this->fail('You do not have permission to perform this action.', 403);
        }
    }

    protected function userPermissions(): array
    {
        if (!$this->user) return [];
        $businessId = $this->businessId();
        if (!$businessId) return [];

        $row = DB::selectOne(
            "SELECT permissions FROM business_users WHERE business_id = ? AND user_id = ? AND active = 1 LIMIT 1",
            [$businessId, $this->userId()]
        );

        return $row && $row->permissions ? json_decode($row->permissions, true) : [];
    }

    protected function validate(array $rules, array $messages = []): void
    {
        $validator = Validator::make($this->input, $rules, $messages);
        if ($validator->fails())
            $this->raiseError($validator->firstError(), 422);
    }

    protected function success(mixed $data = null, string $message = 'Success'): array
    {
        $response = ['success' => true, 'message' => $message];
        if ($data !== null) $response['data'] = $data;
        return $response;
    }

    protected function fail(string $message, int $code = 400): never
    {
        $this->raiseError($message, $code);
    }

    protected function sql(string $name, array $input = [], string $db = ''): Sql
    {
        return (new Sql())->load($name, $input, $db);
    }
}
