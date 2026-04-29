<?php

namespace Tests\Unit;

use App\Base\Query;
use PHPUnit\Framework\TestCase;

class QueryTest extends TestCase
{
    // ── Basic SQL generation ──────────────────────────────────────────────────

    public function test_simple_select_from(): void
    {
        $q = (new Query('Test'))
            ->from('invoices i')
            ->select('list', 'i.id, i.number, i.total');

        $sql = (string)$q;
        $this->assertStringContainsString('SELECT', $sql);
        $this->assertStringContainsString('i.id, i.number, i.total', $sql);
        $this->assertStringContainsString('FROM invoices i', $sql);
    }

    public function test_where_clause_added(): void
    {
        $q = (new Query('Test'))
            ->from('invoices i')
            ->select('list', 'i.id')
            ->filter('i.business_id = 1');

        $sql = (string)$q;
        $this->assertStringContainsString('WHERE', $sql);
        $this->assertStringContainsString('i.business_id = 1', $sql);
    }

    public function test_multiple_filters_use_and(): void
    {
        $q = (new Query('Test'))
            ->from('invoices i')
            ->select('list', 'i.id')
            ->filter('i.business_id = 1')
            ->filter('i.status = \'sent\'');

        $sql = (string)$q;
        $this->assertStringContainsString('WHERE', $sql);
        $this->assertStringContainsString('AND', $sql);
    }

    public function test_left_join_included(): void
    {
        $q = (new Query('Test'))
            ->from('invoices i')
            ->left('clients c ON c.id = i.client_id')
            ->select('list', 'i.id, c.name');

        $sql = (string)$q;
        $this->assertStringContainsString('LEFT JOIN clients c ON c.id = i.client_id', $sql);
    }

    public function test_order_by_included(): void
    {
        $q = (new Query('Test'))
            ->from('invoices i')
            ->select('list', 'i.id')
            ->order('i.created_at', 'desc');

        $sql = (string)$q;
        $this->assertStringContainsString('ORDER BY', $sql);
        $this->assertStringContainsString('i.created_at', $sql);
    }

    public function test_group_by_included(): void
    {
        $q = (new Query('Test'))
            ->from('invoices i')
            ->select('list', 'i.client_id, SUM(i.total)')
            ->group('i.client_id');

        $sql = (string)$q;
        $this->assertStringContainsString('GROUP BY i.client_id', $sql);
    }

    public function test_straight_join_keyword(): void
    {
        $q = (new Query('Test'))
            ->from('invoices i')
            ->select('list', 'i.id')
            ->straightJoin();

        $sql = (string)$q;
        $this->assertStringContainsString('SELECT STRAIGHT_JOIN', $sql);
    }

    public function test_assign_raw_query(): void
    {
        $raw = 'SELECT 1 AS one';
        $q = (new Query('Test'))->assign($raw);
        $this->assertSame($raw, (string)$q);
    }

    // ── select type ───────────────────────────────────────────────────────────

    public function test_select_type_switches_columns(): void
    {
        $q = (new Query('Test'))
            ->from('invoices i')
            ->select('list',   'i.id, i.number')
            ->select('entity', 'i.*')
            ->select('total',  'COUNT(*) AS total');

        $q->assignKeys(['select_type' => 'entity']);
        $this->assertStringContainsString('i.*', (string)$q);
    }

    public function test_invalid_select_type_raises_exception(): void
    {
        $this->expectException(\Exception::class);
        $q = (new Query('Test'))
            ->from('invoices i')
            ->select('list', 'i.id');

        $q->assignKeys(['select_type' => 'nonexistent']);
        (string)$q;
    }

    public function test_total_select_type_omits_order_and_limit(): void
    {
        $q = (new Query('Test'))
            ->from('invoices i')
            ->select('list', 'i.id')
            ->select('total', 'COUNT(*) AS total')
            ->order('i.id', 'desc');

        $q->assignKeys(['select_type' => 'total', 'limit' => 10]);
        $sql = (string)$q;
        $this->assertStringNotContainsString('ORDER BY', $sql);
        $this->assertStringNotContainsString('LIMIT', $sql);
    }

    // ── getLimit ──────────────────────────────────────────────────────────────

    public function test_get_limit_no_limit(): void
    {
        $q = (new Query('Test'))
            ->from('t')
            ->select('list', 't.id');
        $this->assertSame('', $q->getLimit());
    }

    public function test_get_limit_first_page(): void
    {
        $q = (new Query('Test'))
            ->from('t')
            ->select('list', 't.id');
        $q->assignKeys(['limit' => 20]);
        $this->assertSame('0,20', $q->getLimit());
    }

    public function test_get_limit_page_2(): void
    {
        $q = (new Query('Test'))
            ->from('t')
            ->select('list', 't.id');
        $q->assignKeys(['limit' => 10, 'page' => 2]);
        $this->assertSame('10,10', $q->getLimit());
    }

    public function test_get_limit_page_3(): void
    {
        $q = (new Query('Test'))
            ->from('t')
            ->select('list', 't.id');
        $q->assignKeys(['limit' => 15, 'page' => 3]);
        $this->assertSame('30,15', $q->getLimit());
    }

    public function test_get_limit_page_0_treated_as_first_page(): void
    {
        $q = (new Query('Test'))
            ->from('t')
            ->select('list', 't.id');
        $q->assignKeys(['limit' => 5, 'page' => 0]);
        $this->assertSame('0,5', $q->getLimit());
    }

    // ── getRequestField (via reflection) ──────────────────────────────────────

    private function callGetRequestField(string $field): string
    {
        $q = new Query('Test');
        $ref = new \ReflectionMethod($q, 'getRequestField');
        $ref->setAccessible(true);
        return $ref->invoke($q, $field);
    }

    public function test_filter_dot_converts_to_underscore(): void
    {
        $this->assertSame('filter_status', $this->callGetRequestField('filter.status'));
    }

    public function test_filter_dot_search_converts(): void
    {
        $this->assertSame('filter_search', $this->callGetRequestField('filter.search'));
    }

    public function test_filter_dot_client_id_converts(): void
    {
        $this->assertSame('filter_client_id', $this->callGetRequestField('filter.client_id'));
    }

    public function test_filter_underscore_passthrough(): void
    {
        $this->assertSame('filter_active', $this->callGetRequestField('filter_active'));
    }

    public function test_allowed_key_sort_by(): void
    {
        $this->assertSame('sort_by', $this->callGetRequestField('sort_by'));
    }

    public function test_allowed_key_sort_order(): void
    {
        $this->assertSame('sort_order', $this->callGetRequestField('sort_order'));
    }

    public function test_allowed_key_select_type(): void
    {
        $this->assertSame('select_type', $this->callGetRequestField('select_type'));
    }

    public function test_unknown_field_returns_empty_string(): void
    {
        $this->assertSame('', $this->callGetRequestField('business_id'));
    }

    public function test_unknown_field_id_returns_empty_string(): void
    {
        $this->assertSame('', $this->callGetRequestField('id'));
    }

    // ── HAVING ────────────────────────────────────────────────────────────────

    public function test_having_clause_included(): void
    {
        $q = (new Query('Test'))
            ->from('invoices i')
            ->select('list', 'i.client_id, COUNT(*) AS cnt')
            ->group('i.client_id')
            ->having('cnt > 5');

        $sql = (string)$q;
        $this->assertStringContainsString('HAVING cnt > 5', $sql);
    }

    // ── UNION ─────────────────────────────────────────────────────────────────

    public function test_union_combines_queries(): void
    {
        $q = (new Query('Test'))
            ->union('SELECT 1 AS id')
            ->union('SELECT 2 AS id');

        $sql = (string)$q;
        $this->assertStringContainsString('UNION', $sql);
        $this->assertStringContainsString('SELECT 1 AS id', $sql);
        $this->assertStringContainsString('SELECT 2 AS id', $sql);
    }
}
