<?php

namespace App\Task;

use App\Base\Task;
use App\Core\DB;
use App\Core\InventoryStock;

class Inventory extends Task
{
    protected bool $useTransaction = true;

    public function overview(array $input): array
    {
        $businessId = $this->requireBusiness();
        $defaultId = InventoryStock::defaultLocation($businessId);
        $settings = InventoryStock::settings($businessId);
        $locationId = (int)($input['location_id'] ?? 0);
        $whereLocation = $locationId ? ' AND l.id = ?' : '';
        $params = $locationId ? [$businessId, $locationId] : [$businessId];
        $locations = DB::select("SELECT id, name, type, address, is_default, active FROM inventory_locations WHERE business_id = ? ORDER BY active DESC, is_default DESC, name", [$businessId]);
        $stock = DB::select("SELECT p.id product_id, p.name, p.sku, p.barcode, p.unit, p.purchase_price, p.mrp, p.reorder_level, p.batch_tracking, p.expiry_tracking, p.serial_tracking, l.id location_id, l.name location_name, pl.price location_price, COALESCE(sb.quantity,0) quantity, COALESCE(sb.reserved_quantity,0) reserved_quantity, COALESCE(sb.average_cost,p.purchase_price,0) average_cost, lm.last_moved_at FROM products p CROSS JOIN inventory_locations l LEFT JOIN product_locations pl ON pl.product_id=p.id AND pl.location_id=l.id LEFT JOIN stock_balances sb ON sb.business_id=p.business_id AND sb.product_id=p.id AND sb.location_id=l.id LEFT JOIN (SELECT business_id,product_id,location_id,MAX(occurred_at) last_moved_at FROM stock_movements GROUP BY business_id,product_id,location_id) lm ON lm.business_id=p.business_id AND lm.product_id=p.id AND lm.location_id=l.id WHERE p.business_id=? AND p.active=1 AND p.track_stock=1 AND l.active=1{$whereLocation} ORDER BY p.name,l.name", $params);
        $summary = ['products' => 0, 'low' => 0, 'out' => 0, 'dead' => 0, 'value' => 0.0];
        $seen = []; $deadSeen = [];
        foreach ($stock as $row) {
            $seen[$row->product_id] = true;
            if ((float)$row->quantity <= 0) $summary['out']++;
            elseif ((float)$row->quantity <= (float)$row->reorder_level) $summary['low']++;
            $summary['value'] += (float)$row->quantity * (float)$row->average_cost;
            if ((float)$row->quantity > 0 && $row->last_moved_at && strtotime($row->last_moved_at) < strtotime('-90 days') && empty($deadSeen[$row->product_id])) {
                $summary['dead']++; $deadSeen[$row->product_id] = true;
            }
        }
        $summary['products'] = count($seen);
        $movements = DB::select('SELECT sm.*, p.name product_name, p.unit, l.name location_name, u.name user_name FROM stock_movements sm JOIN products p ON p.id=sm.product_id JOIN inventory_locations l ON l.id=sm.location_id LEFT JOIN users u ON u.id=sm.created_by WHERE sm.business_id=? ORDER BY sm.id DESC LIMIT 100', [$businessId]);
        $transfers = DB::select('SELECT st.*, f.name from_name, t.name to_name FROM stock_transfers st JOIN inventory_locations f ON f.id=st.from_location_id JOIN inventory_locations t ON t.id=st.to_location_id WHERE st.business_id=? ORDER BY st.id DESC LIMIT 50', [$businessId]);

        // Hide cost/value from cashiers and staff (only owner/admin/accountant see financials)
        $role = $this->userRole();
        if (!in_array($role, ['owner', 'admin', 'accountant'], true)) {
            $summary['value'] = null;
            foreach ($stock as &$row) { $row->purchase_price = null; $row->average_cost = null; $row->mrp = null; }
            foreach ($movements as &$m) { $m->unit_cost = null; }
        }

        return $this->success(compact('settings','defaultId','locations','stock','summary','movements','transfers'));
    }

    public function saveSettings(array $input): array
    {
        $businessId = $this->requireBusiness();
        $this->requireRole(['owner','admin']);
        $mode = in_array($input['mode'] ?? '', ['none','warn','strict'], true) ? $input['mode'] : 'none';
        DB::statement('UPDATE businesses SET inventory_mode=?, inventory_advanced=? WHERE id=?', [$mode, !empty($input['advanced']) ? 1 : 0, $businessId]);
        InventoryStock::defaultLocation($businessId);
        return $this->success(null, 'Stock settings saved.');
    }

    public function saveLocation(array $input): array
    {
        $businessId = $this->requireBusiness(); $this->requireRole(['owner','admin']);
        $name = trim($input['name'] ?? ''); if ($name === '') $this->fail('Shop / Godown name is required.');
        $type = in_array($input['type'] ?? '', ['shop','godown','damaged'], true) ? $input['type'] : 'shop';
        if (!empty($input['id'])) DB::statement('UPDATE inventory_locations SET name=?, type=?, address=?, active=? WHERE id=? AND business_id=?', [$name,$type,$input['address']??null,!empty($input['active'])?1:0,(int)$input['id'],$businessId]);
        else DB::statement('INSERT INTO inventory_locations (business_id,name,type,address,is_default) VALUES (?,?,?,?,?)', [$businessId,$name,$type,$input['address']??null,!empty($input['is_default'])?1:0]);
        if (!empty($input['is_default'])) { $id = (int)($input['id'] ?? DB::lastInsertId()); DB::statement('UPDATE inventory_locations SET is_default=(id=?) WHERE business_id=?', [$id,$businessId]); }
        return $this->success(null, 'Shop / Godown saved.');
    }

    public function deleteLocation(array $input): array
    {
        $businessId = $this->requireBusiness(); $this->requireRole(['owner','admin']);
        $id = (int)($input['id'] ?? 0); if (!$id) $this->fail('Location ID is required.');
        $loc = DB::selectOne('SELECT * FROM inventory_locations WHERE id=? AND business_id=?', [$id, $businessId]);
        if (!$loc) $this->fail('Location not found.');
        if (+($loc->is_default ?? 0)) $this->fail('Cannot delete the default location. Set another location as default first.');
        $hasStock = DB::selectOne('SELECT 1 FROM stock_balances WHERE location_id=? AND quantity != 0 LIMIT 1', [$id]);
        if ($hasStock) $this->fail('Cannot delete a location that has stock. Move or adjust stock to zero first.');
        DB::statement('DELETE FROM inventory_locations WHERE id=? AND business_id=?', [$id, $businessId]);
        return $this->success(null, 'Location deleted.');
    }

    public function adjust(array $input): array
    {
        $businessId=$this->requireBusiness(); $this->requireRole(['owner','admin','accountant']);
        $productId=(int)($input['product_id']??0); $locationId=(int)($input['location_id']??InventoryStock::defaultLocation($businessId));
        $quantity=(float)($input['quantity']??0); $kind=$input['kind']??'adjustment';
        if (!in_array($kind,['opening','purchase','purchase_return','adjustment','damage','count'],true)) $kind='adjustment';
        if (in_array($kind,['damage','purchase_return'],true) && $quantity>0) $quantity=-$quantity;
        if ($kind==='count') {
            $currentRow=DB::selectOne('SELECT quantity FROM stock_balances WHERE business_id=? AND location_id=? AND product_id=?',[$businessId,$locationId,$productId]);
            $current=(float)($currentRow->quantity??0);
            $input['note']=trim(($input['note']??'').' Physical count set to '.$quantity);
            $quantity=round($quantity-$current,3);
            if(abs($quantity)<0.0005)return $this->success(null,'Stock already matches the physical count.');
        }
        InventoryStock::move($businessId,$locationId,$productId,$quantity,$kind,'manual',null,$this->userId(),$input);
        return $this->success(null,'Stock updated.');
    }

    public function createTransfer(array $input): array
    {
        $businessId=$this->requireBusiness(); $this->requireRole(['owner','admin','accountant']);
        $from=(int)($input['from_location_id']??0); $to=(int)($input['to_location_id']??0); if(!$from||!$to||$from===$to)$this->fail('Choose two different locations.');
        $items=array_values(array_filter($input['items']??[],fn($i)=>(int)($i['product_id']??0)>0&&(float)($i['quantity']??0)>0)); if(!$items)$this->fail('Add at least one product.');
        $number='TR-'.date('Ymd').'-'.str_pad((string)((int)(DB::selectOne('SELECT COUNT(*) c FROM stock_transfers WHERE business_id=?',[$businessId])->c??0)+1),4,'0',STR_PAD_LEFT);
        DB::statement('INSERT INTO stock_transfers (business_id,transfer_no,from_location_id,to_location_id,transfer_date,notes,created_by) VALUES (?,?,?,?,?,?,?)',[$businessId,$number,$from,$to,$input['transfer_date']??date('Y-m-d'),$input['notes']??null,$this->userId()]);
        $id=(int)DB::lastInsertId(); foreach($items as $i) DB::statement('INSERT INTO stock_transfer_items (transfer_id,product_id,quantity,batch_no,expiry_date,serial_numbers) VALUES (?,?,?,?,?,?)',[$id,(int)$i['product_id'],(float)$i['quantity'],$i['batch_no']??null,$i['expiry_date']?:null,json_encode((array)($i['serial_numbers']??[]))]);
        return $this->success(['id'=>$id,'number'=>$number],'Stock transfer created.');
    }

    public function dispatchTransfer(array $input): array
    {
        $businessId=$this->requireBusiness(); $transfer=$this->transfer((int)($input['id']??0),$businessId);
        if($transfer->status!=='draft')$this->fail('Only a draft transfer can be sent.');
        $items=DB::select('SELECT * FROM stock_transfer_items WHERE transfer_id=?',[$transfer->id]);
        foreach($items as $i) InventoryStock::move($businessId,(int)$transfer->from_location_id,(int)$i->product_id,-(float)$i->quantity,'transfer_out','stock_transfer',(int)$transfer->id,$this->userId(),(array)$i);
        DB::statement("UPDATE stock_transfers SET status='dispatched',dispatched_at=NOW() WHERE id=?",[$transfer->id]); return $this->success(null,'Stock sent. It is now in transit.');
    }

    public function receiveTransfer(array $input): array
    {
        $businessId=$this->requireBusiness(); $transfer=$this->transfer((int)($input['id']??0),$businessId);
        if($transfer->status!=='dispatched')$this->fail('Only dispatched stock can be received.');
        $items=DB::select('SELECT * FROM stock_transfer_items WHERE transfer_id=?',[$transfer->id]);
        foreach($items as $i){$qty=(float)$i->quantity; InventoryStock::move($businessId,(int)$transfer->to_location_id,(int)$i->product_id,$qty,'transfer_in','stock_transfer_receipt',(int)$transfer->id,$this->userId(),(array)$i); DB::statement('UPDATE stock_transfer_items SET received_quantity=? WHERE id=?',[$qty,$i->id]);}
        DB::statement("UPDATE stock_transfers SET status='received',received_at=NOW(),received_by=? WHERE id=?",[$this->userId(),$transfer->id]); return $this->success(null,'Stock received at destination.');
    }

    private function transfer(int $id,int $businessId): object { $row=DB::selectOne('SELECT * FROM stock_transfers WHERE id=? AND business_id=?',[$id,$businessId]); if(!$row)$this->fail('Transfer not found.',404); return $row; }
}
