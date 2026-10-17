<?php

namespace App\Task;

use App\Base\Task;
use App\Core\Auth;
use App\Core\DB;

final class CashierShift extends Task
{
    protected bool $useTransaction = true;

    public function current(array $input): array
    {
        $businessId = $this->requireBusiness();
        if (!Auth::deviceId() || !Auth::counterId()) return $this->success(['required'=>false,'shift'=>null]);
        $shift = DB::selectOne(
            "SELECT s.*,c.name counter_name,c.code counter_code,u.name cashier_name
             FROM cashier_shifts s INNER JOIN billing_counters c ON c.id=s.counter_id
             INNER JOIN users u ON u.id=s.cashier_user_id
             WHERE s.business_id=? AND s.device_id=? AND s.cashier_user_id=? AND s.status='open'
             ORDER BY s.id DESC LIMIT 1",
            [$businessId,Auth::deviceId(),$this->userId()]
        );
        if ($shift) {
            $totals = DB::selectOne("SELECT COUNT(DISTINCT invoice_id) invoice_count,COALESCE(SUM(CASE WHEN method='cash' THEN amount ELSE 0 END),0) cash_sales,COALESCE(SUM(CASE WHEN method='upi' THEN amount ELSE 0 END),0) upi_sales,COALESCE(SUM(CASE WHEN method='card' THEN amount ELSE 0 END),0) card_sales,COALESCE(SUM(CASE WHEN method NOT IN ('cash','upi','card') THEN amount ELSE 0 END),0) other_sales FROM payments WHERE business_id=? AND shift_id=?", [$businessId,$shift->id]);
            $shift->invoice_count = (int)$totals->invoice_count;
            $shift->cash_sales = (float)$totals->cash_sales;
            $shift->upi_sales = (float)$totals->upi_sales;
            $shift->card_sales = (float)$totals->card_sales;
            $shift->other_sales = (float)$totals->other_sales;
            $shift->expected_cash = round((float)$shift->opening_cash + (float)$totals->cash_sales, 2);
        }
        return $this->success(['required'=>true,'shift'=>$shift]);
    }

    public function open(array $input): array
    {
        $businessId = $this->requireBusiness();
        if (!Auth::deviceId() || !Auth::counterId()) $this->fail('Cashier shifts are available on paired Counter PCs.', 422);
        $opening = round((float)($input['opening_cash'] ?? 0), 2);
        if ($opening < 0) $this->fail('Opening cash cannot be negative.', 422);
        $existing = DB::selectOne("SELECT id,cashier_user_id FROM cashier_shifts WHERE business_id=? AND device_id=? AND status='open' LIMIT 1 FOR UPDATE", [$businessId,Auth::deviceId()]);
        if ($existing) {
            if ((int)$existing->cashier_user_id === (int)$this->userId()) return $this->current([]);
            $this->fail('Another cashier shift is already open on this counter. Ask the owner to close it.', 409);
        }
        DB::statement(
            "INSERT INTO cashier_shifts (business_id,counter_id,device_id,location_id,cashier_user_id,opening_cash,status,opened_at) VALUES (?,?,?,?,?,?,'open',NOW())",
            [$businessId,Auth::counterId(),Auth::deviceId(),Auth::counterLocationId(),$this->userId(),$opening]
        );
        return $this->current([]);
    }

    public function close(array $input): array
    {
        $businessId = $this->requireBusiness();
        $counted = round((float)($input['counted_cash'] ?? 0), 2);
        if ($counted < 0) $this->fail('Counted cash cannot be negative.', 422);
        $shift = DB::selectOne("SELECT * FROM cashier_shifts WHERE business_id=? AND device_id=? AND cashier_user_id=? AND status='open' LIMIT 1 FOR UPDATE", [$businessId,Auth::deviceId(),$this->userId()]);
        if (!$shift) $this->fail('No open shift found for this cashier.', 404);
        $totals = DB::selectOne(
            "SELECT COUNT(DISTINCT invoice_id) invoice_count,
             COALESCE(SUM(CASE WHEN method='cash' THEN amount ELSE 0 END),0) cash_sales,
             COALESCE(SUM(CASE WHEN method='upi' THEN amount ELSE 0 END),0) upi_sales,
             COALESCE(SUM(CASE WHEN method='card' THEN amount ELSE 0 END),0) card_sales,
             COALESCE(SUM(CASE WHEN method NOT IN ('cash','upi','card') THEN amount ELSE 0 END),0) other_sales
             FROM payments WHERE business_id=? AND shift_id=?",
            [$businessId,$shift->id]
        );
        $expected = round((float)$shift->opening_cash + (float)$totals->cash_sales, 2);
        $variance = round($counted - $expected, 2);
        DB::statement(
            "UPDATE cashier_shifts SET status='closed',invoice_count=?,cash_sales=?,upi_sales=?,card_sales=?,other_sales=?,expected_cash=?,counted_cash=?,cash_variance=?,closing_note=?,closed_at=NOW(),closed_by=? WHERE id=?",
            [(int)$totals->invoice_count,$totals->cash_sales,$totals->upi_sales,$totals->card_sales,$totals->other_sales,$expected,$counted,$variance,substr(trim($input['closing_note'] ?? ''),0,500) ?: null,$this->userId(),$shift->id]
        );
        $closed = DB::selectOne('SELECT * FROM cashier_shifts WHERE id=?', [$shift->id]);
        return $this->success(['shift'=>$closed], 'Shift closed and cash checked.');
    }

    public function history(array $input): array
    {
        $businessId = $this->requireBusiness();
        $this->requireRole(['owner','admin']);
        return $this->success(DB::select(
            "SELECT s.*,c.name counter_name,d.device_name,u.name cashier_name,cu.name closed_by_name
             FROM cashier_shifts s INNER JOIN billing_counters c ON c.id=s.counter_id
             INNER JOIN business_devices d ON d.id=s.device_id INNER JOIN users u ON u.id=s.cashier_user_id
             LEFT JOIN users cu ON cu.id=s.closed_by WHERE s.business_id=? ORDER BY s.opened_at DESC LIMIT 200",
            [$businessId]
        ));
    }
}
