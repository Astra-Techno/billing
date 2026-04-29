<?php

namespace App\Base;

use App\Core\Auth;
use App\Core\DB;
use App\Core\RequestHolder;

class Query extends ClassObject
{
    protected string $name;
    protected ?string $from        = null;
    protected array   $join        = [];
    protected array   $where       = [];
    protected array   $whereGroup  = [];
    protected array   $select      = [];
    protected ?string $group       = null;
    protected string  $orderBy     = '';
    protected string  $order       = '';
    protected ?string $query       = null;
    protected string  $select_type = 'list';
    protected ?int    $limit       = null;
    protected ?int    $page        = null;
    protected array   $having      = [];
    protected array   $union       = [];
    protected bool    $straightJoin = false;

    public function __construct(string $name)
    {
        $this->name = 'SQL:' . $name;
    }

    public function getName(): string { return $this->name; }

    public function assignKeys(array $input): void
    {
        foreach (['select_type', 'limit', 'page'] as $key) {
            if (isset($input[$key]))
                $this->$key = $input[$key];
        }
    }

    public function assign(string $query): static
    {
        $this->query = $query;
        return $this;
    }

    public function from(string $str): static     { $this->set('from', $str); return $this; }
    public function select(string $name, string $value): static { $this->select[$name] = $value; return $this; }
    public function join(string $str): static     { if ($str) $this->join[] = 'JOIN '       . $str; return $this; }
    public function inner(string $str): static    { if ($str) $this->join[] = 'INNER JOIN '  . $str; return $this; }
    public function left(string $str): static     { if ($str) $this->join[] = 'LEFT JOIN '   . $str; return $this; }
    public function straightJoin(): static        { $this->straightJoin = true; return $this; }
    public function group(string $str): static    { $this->set('group', $str); return $this; }
    public function having(string $str): static   { $this->having[] = $str; return $this; }
    public function union(string $query): static  { $this->union[]  = $query; return $this; }

    public function filter(string $str, bool $required = true): static
    {
        if ($str) $this->where[] = ['condition' => $str, 'required' => $required];
        return $this;
    }

    public function filterOptional(string $str): static
    {
        return $this->filter($str, false);
    }

    public function filterAnyOneRequired(string $type, array $array): static
    {
        $this->whereGroup[$type] = $array;
        return $this;
    }

    public function order(string $field, string $direction = 'asc'): static
    {
        $this->set('orderBy', $field);
        $this->set('order', $direction);
        return $this;
    }

    // ── Build SQL ─────────────────────────────────────────────────────────────

    public function getSelect(): string
    {
        $type = trim($this->select_type);
        if ($type && !array_key_exists($type, $this->select))
            $this->raiseError($this->name . ' - Invalid select type (' . $type . ')!');
        return $this->select[$type];
    }

    public function getGroup(): string
    {
        if ($this->select_type === 'total') return '';
        return $this->group ?? '';
    }

    public function getOrder(): string
    {
        if ($this->select_type === 'total') return '';
        return trim($this->orderBy);
    }

    public function getLimit(): string
    {
        $limit = (int)($this->limit ?? 0);
        if ($this->select_type === 'total' || !$limit) return '';
        $page = (int)($this->page ?? 0);
        $from = $page > 0 ? ($page - 1) * $limit : 0;
        return "{$from},{$limit}";
    }

    public function __toString(): string
    {
        if ($this->query) return $this->query;

        if (!empty($this->union))
            return implode(" \nUNION \n", $this->union);

        $keyword = $this->straightJoin ? 'SELECT STRAIGHT_JOIN' : 'SELECT';
        $parts   = [$keyword, "\t" . $this->getSelect()];

        $from = $this->from ?? '';
        $parts[] = str_contains(strtoupper($from), 'FROM ') ? $from : 'FROM ' . $from;

        foreach ($this->join as $join)
            $parts[] = $join;

        $this->where = array_values($this->where);
        foreach ($this->where as $i => $w)
            $parts[] = $i === 0 ? "WHERE\n\t({$w['condition']})" : "\tAND ({$w['condition']})";

        if ($g = $this->getGroup())
            $parts[] = 'GROUP BY ' . $g;

        if (!empty($this->having))
            $parts[] = 'HAVING ' . implode(' AND ', $this->having);

        if ($o = $this->getOrder())
            $parts[] = 'ORDER BY ' . $o;

        if ($l = $this->getLimit())
            $parts[] = 'LIMIT ' . $l;

        $sql = implode(" \n", $parts);

        if (debugMode())
            LogManager::logInfo('Queries', $sql);

        return $sql;
    }

    // ── Bind input ────────────────────────────────────────────────────────────

    public function bind(array $data): void
    {
        if ($this->query) {
            $failed = [];
            $this->query = $this->replaceConstant($this->query, $data, $failed, false);
            if (!$this->query && $failed)
                $this->raiseError($this->name . ' - required inputs (' . implode(', ', $failed) . ') missing!');
            return;
        }

        // Bind JOINs
        foreach ($this->join as $i => $join) {
            $failed = [];
            $str = $this->replaceConstant($join, $data, $failed, true);
            if ($str && !$failed) {
                $this->join[$i] = $str;
            } else {
                unset($this->join[$i]);
            }
        }

        // Bind WHERE
        foreach ($this->where as $i => $w) {
            $failed = [];
            $str = $this->replaceConstant($w['condition'], $data, $failed, true);

            if ($str && !$failed) {
                $this->where[$i]['condition'] = $str;
                continue;
            }

            if ($w['required'] && $failed)
                $this->raiseError($this->name . ' - required inputs (' . implode(', ', $failed) . ') missing!');

            unset($this->where[$i]);
        }

        // Bind WHERE groups
        foreach ($this->whereGroup as $groupKey => $conditions) {
            $added = false;
            foreach ($conditions as $condition) {
                $failed = [];
                $str = $this->replaceConstant($condition, $data, $failed, true);
                if ($str && !$failed) {
                    $this->where[] = ['condition' => $str, 'required' => true];
                    $added = true;
                }
            }
            if (!$added)
                $this->raiseError($this->name . " - group ({$groupKey}) no conditions matched!");
        }

        // Bind ORDER
        $failed = [];
        $this->orderBy = $this->replaceConstant($this->orderBy, $data, $failed, false);
        $this->order   = $this->replaceConstant($this->order,   $data, $failed, false);

        $this->order = strtoupper(trim($this->order));
        if (!in_array($this->order, ['ASC', 'DESC'])) $this->order = '';

        $this->orderBy .= ' ' . $this->order;
        if ($failed) $this->orderBy = '';
    }

    // ── Placeholder replacement ───────────────────────────────────────────────

    public function replaceConstant(string $str, array $input, array &$failed = [], bool $isSql = false): string
    {
        preg_match_all('/{(.*?)}/', $str, $matches);
        if (empty($matches[1]) || str_contains($str, '{"')) return $str;

        $request = RequestHolder::all();
        $find = $replace = [];

        foreach (array_unique($matches[1]) as $match) {
            $requestField = $this->getRequestField($match);

            if ($requestField && array_key_exists($requestField, $request))
                $value = $request[$requestField];
            elseif (array_key_exists($match, $input))
                $value = $input[$match];
            elseif ($match === 'business_id')
                $value = Auth::businessId();
            elseif ($match === 'user_id')
                $value = Auth::id();
            else {
                $failed[] = $match;
                continue;
            }

            // Array → IN clause
            if (is_array($value)) {
                if ($isSql && str_contains(strtoupper($str), 'IN (')) {
                    $pdo    = DB::getPdo();
                    $quoted = array_map(
                        fn($v) => is_numeric($v) ? $v : $pdo->quote($v),
                        $value
                    );
                    $value = implode(',', $quoted);
                } else {
                    $value = implode(',', $value);
                }
            } elseif ($isSql && !$this->isInt($value)) {
                $value = DB::getPdo()->quote((string)$value);
            }

            $find[]    = '{' . $match . '}';
            $replace[] = $value;
        }

        return str_replace($find, $replace, $str);
    }

    private function getRequestField(string $field): string
    {
        $allowed = ['sort_by', 'sort_order', 'select_type'];
        // PHP's parse_str converts dots to underscores in query param keys,
        // so {filter.status} must look up 'filter_status' in the request.
        if (str_starts_with($field, 'filter.'))
            return str_replace('.', '_', $field);
        if (str_starts_with($field, 'filter_'))
            return $field;
        if (in_array($field, $allowed))
            return $field;
        return '';
    }

    private function isInt(mixed $var): bool
    {
        return (bool) preg_match('/^\d+(,\d+)*$/', trim(str_replace(' ', '', (string)$var)));
    }
}
