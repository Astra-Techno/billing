<?php

namespace App\Task;

use App\Base\Task;
use App\Core\DB;

class BillingCounter extends Task
{
    protected bool $useTransaction = true;

    private function ownerAccess(): int
    {
        $businessId = $this->requireBusiness();
        $this->requireRole(['owner', 'admin']);
        return $businessId;
    }

    private function mainPcOnly(): void
    {
        if (($_ENV['DESKTOP_MODE'] ?? '') !== 'true' || ($_ENV['DESKTOP_NETWORK_MODE'] ?? 'SinglePc') !== 'MainPc') $this->fail('Counter pairing is available only when this installation is configured as the Main PC.', 403);
    }

    private function uuid(): string
    {
        $bytes = random_bytes(16);
        $bytes[6] = chr((ord($bytes[6]) & 0x0f) | 0x40);
        $bytes[8] = chr((ord($bytes[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($bytes), 4));
    }

    private function event(int $businessId, ?int $deviceId, string $action, array $metadata = []): void
    {
        DB::statement(
            'INSERT INTO device_audit_events (business_id,device_id,action,actor_user_id,metadata,ip_address,created_at) VALUES (?,?,?,?,?,?,NOW())',
            [$businessId, $deviceId, $action, $this->userId(), $metadata ? json_encode($metadata, JSON_UNESCAPED_UNICODE) : null, $_SERVER['REMOTE_ADDR'] ?? null]
        );
    }

    public function overview(array $input): array
    {
        $businessId = $this->ownerAccess();
        DB::statement("UPDATE counter_pairing_requests SET status='expired' WHERE business_id=? AND status IN ('open','pending') AND expires_at<NOW()", [$businessId]);
        $counters = DB::select(
            'SELECT c.*,l.name location_name,COUNT(d.id) device_count,MAX(d.last_seen_at) last_seen_at FROM billing_counters c LEFT JOIN inventory_locations l ON l.id=c.location_id AND l.business_id=c.business_id LEFT JOIN business_devices d ON d.counter_id=c.id AND d.business_id=c.business_id AND d.status=\'active\' WHERE c.business_id=? GROUP BY c.id,l.name ORDER BY c.active DESC,c.name',
            [$businessId]
        );
        $devices = DB::select(
            'SELECT d.id,d.counter_id,d.device_uuid,d.device_name,d.device_type,d.platform,d.app_version,d.status,d.approved_at,d.last_seen_at,d.created_at,c.name counter_name FROM business_devices d LEFT JOIN billing_counters c ON c.id=d.counter_id AND c.business_id=d.business_id WHERE d.business_id=? ORDER BY FIELD(d.status,\'pending\',\'active\',\'inactive\',\'revoked\'),d.created_at DESC',
            [$businessId]
        );
        $pairing = DB::selectOne(
            "SELECT request_uuid,status,expires_at,created_at FROM counter_pairing_requests WHERE business_id=? AND status IN ('open','pending') AND expires_at>NOW() ORDER BY id DESC LIMIT 1",
            [$businessId]
        );
        $locations = DB::select('SELECT id,name,type FROM inventory_locations WHERE business_id=? AND active=1 ORDER BY is_default DESC,name', [$businessId]);
        return $this->success(['counters'=>$counters, 'devices'=>$devices, 'pairing'=>$pairing, 'locations'=>$locations]);
    }

    public function saveCounter(array $input): array
    {
        $businessId = $this->ownerAccess();
        $this->validate(['name'=>'required|string|max:100']);
        $id = (int)($input['id'] ?? 0);
        $name = trim($input['name']);
        $code = strtoupper(trim($input['code'] ?? ''));
        if (!$code) $code = 'C' . str_pad((string)((int)(DB::selectOne('SELECT COUNT(*) total FROM billing_counters WHERE business_id=?', [$businessId])->total ?? 0) + 1), 2, '0', STR_PAD_LEFT);
        if (!preg_match('/^[A-Z0-9_-]{1,20}$/', $code)) $this->fail('Counter code may contain only letters, numbers, dash and underscore.', 422);
        $locationId = !empty($input['location_id']) ? (int)$input['location_id'] : null;
        if ($locationId && !DB::selectOne('SELECT id FROM inventory_locations WHERE id=? AND business_id=? AND active=1', [$locationId,$businessId])) $this->fail('Shop location not found.', 422);
        if ($id) {
            if (!DB::selectOne('SELECT id FROM billing_counters WHERE id=? AND business_id=?', [$id,$businessId])) $this->fail('Billing counter not found.', 404);
            DB::statement('UPDATE billing_counters SET name=?,code=?,location_id=?,invoice_prefix=?,updated_at=NOW() WHERE id=? AND business_id=?', [$name,$code,$locationId,trim($input['invoice_prefix'] ?? '') ?: null,$id,$businessId]);
        } else {
            DB::statement('INSERT INTO billing_counters (business_id,location_id,code,name,invoice_prefix,created_by) VALUES (?,?,?,?,?,?)', [$businessId,$locationId,$code,$name,trim($input['invoice_prefix'] ?? '') ?: null,$this->userId()]);
            $id = (int)DB::lastInsertId();
        }
        $this->event($businessId, null, 'counter_saved', ['counter_id'=>$id,'code'=>$code]);
        return $this->success(['id'=>$id], 'Billing counter saved.');
    }

    public function setCounterStatus(array $input): array
    {
        $businessId = $this->ownerAccess();
        $id = (int)($input['id'] ?? 0);
        $active = !empty($input['active']) ? 1 : 0;
        if (!DB::affectingStatement('UPDATE billing_counters SET active=?,updated_at=NOW() WHERE id=? AND business_id=?', [$active,$id,$businessId])) $this->fail('Billing counter not found.', 404);
        if (!$active) DB::statement("UPDATE business_devices SET status='inactive',updated_at=NOW() WHERE counter_id=? AND business_id=? AND status='active'", [$id,$businessId]);
        $this->event($businessId, null, $active ? 'counter_activated' : 'counter_deactivated', ['counter_id'=>$id]);
        return $this->success(null, $active ? 'Billing counter activated.' : 'Billing counter deactivated.');
    }

    public function startPairing(array $input): array
    {
        $this->mainPcOnly();
        $businessId = $this->ownerAccess();
        DB::statement("UPDATE counter_pairing_requests SET status='expired' WHERE business_id=? AND status IN ('open','pending')", [$businessId]);
        $requestUuid = $this->uuid();
        $code = (string)random_int(100000, 999999);
        DB::statement("INSERT INTO counter_pairing_requests (request_uuid,business_id,pairing_code_hash,status,expires_at) VALUES (?,?,?,'open',DATE_ADD(NOW(),INTERVAL 10 MINUTE))", [$requestUuid,$businessId,hash('sha256',$requestUuid.'|'.$code)]);
        $this->event($businessId, null, 'pairing_started', ['request_uuid'=>$requestUuid]);
        return $this->success(['request_id'=>$requestUuid,'pairing_code'=>$code,'expires_in'=>600], 'Pairing code created.');
    }

    public function setDeviceStatus(array $input): array
    {
        $businessId = $this->ownerAccess();
        $id = (int)($input['id'] ?? 0);
        $status = in_array($input['status'] ?? '', ['active','inactive','revoked'], true) ? $input['status'] : '';
        if (!$status) $this->fail('Invalid device status.', 422);
        $device = DB::selectOne('SELECT id,status FROM business_devices WHERE id=? AND business_id=?', [$id,$businessId]);
        if (!$device) $this->fail('Device not found.', 404);
        DB::statement('UPDATE business_devices SET status=?,revoked_at=?,updated_at=NOW() WHERE id=? AND business_id=?', [$status,$status==='revoked'?date('Y-m-d H:i:s'):null,$id,$businessId]);
        $this->event($businessId, $id, 'device_'.$status, ['previous_status'=>$device->status]);
        return $this->success(null, 'Device status updated.');
    }

    public function requestJoin(array $input): array
    {
        $this->mainPcOnly();
        $this->validate([
            'pairing_code'=>'required|string',
            'device_uuid'=>'required|string', 'device_fingerprint'=>'required|string|min_length:32',
            'device_name'=>'required|string|max:120'
        ]);
        $requestId = trim($input['request_id'] ?? '');
        $request = $requestId
            ? DB::selectOne("SELECT * FROM counter_pairing_requests WHERE request_uuid=? AND status='open' LIMIT 1 FOR UPDATE", [$requestId])
            : DB::selectOne("SELECT * FROM counter_pairing_requests WHERE status='open' AND expires_at>NOW() ORDER BY id DESC LIMIT 1 FOR UPDATE");
        if (!$request) $this->fail('Pairing request not found or already used.', 404);
        if (strtotime($request->expires_at) < time()) {
            DB::statement("UPDATE counter_pairing_requests SET status='expired' WHERE id=?", [$request->id]);
            $this->fail('Pairing code expired. Create a new code on the Main PC.', 410);
        }
        $code = preg_replace('/\D/', '', (string)$input['pairing_code']);
        if (!hash_equals($request->pairing_code_hash, hash('sha256', $request->request_uuid.'|'.$code))) $this->fail('Incorrect pairing code.', 422);
        $uuid = strtolower(trim($input['device_uuid']));
        if (!preg_match('/^[a-f0-9-]{36}$/', $uuid)) $this->fail('Invalid device identity.', 422);
        $fingerprintHash = hash('sha256', trim($input['device_fingerprint']));
        $device = DB::selectOne('SELECT id,status FROM business_devices WHERE business_id=? AND device_uuid=? LIMIT 1', [$request->business_id,$uuid]);
        if ($device && $device->status === 'revoked') $this->fail('This device was revoked. Ask the owner to allow it again.', 403);
        if ($device) {
            DB::statement("UPDATE business_devices SET device_fingerprint_hash=?,device_name=?,platform=?,app_version=?,status='pending',credential_hash=NULL,counter_id=NULL,updated_at=NOW() WHERE id=?", [$fingerprintHash,trim($input['device_name']),substr(trim($input['platform'] ?? 'Windows'),0,80),substr(trim($input['app_version'] ?? ''),0,40) ?: null,$device->id]);
            $deviceId = (int)$device->id;
        } else {
            DB::statement("INSERT INTO business_devices (business_id,device_uuid,device_fingerprint_hash,device_name,device_type,platform,app_version,status) VALUES (?,?,?,?,'counter',?,?,'pending')", [(int)$request->business_id,$uuid,$fingerprintHash,trim($input['device_name']),substr(trim($input['platform'] ?? 'Windows'),0,80),substr(trim($input['app_version'] ?? ''),0,40) ?: null]);
            $deviceId = (int)DB::lastInsertId();
        }
        $secret = bin2hex(random_bytes(32));
        DB::statement("UPDATE counter_pairing_requests SET requested_device_id=?,request_secret_hash=?,status='pending',updated_at=NOW() WHERE id=? AND status='open'", [$deviceId,hash('sha256',$secret),$request->id]);
        $this->event((int)$request->business_id, $deviceId, 'pairing_requested', ['request_uuid'=>$request->request_uuid,'device_name'=>trim($input['device_name'])]);
        return $this->success(['request_id'=>$request->request_uuid,'request_secret'=>$secret,'status'=>'pending'], 'Waiting for approval on the Main PC.');
    }

    public function approvePairing(array $input): array
    {
        $businessId = $this->ownerAccess();
        $deviceId = (int)($input['device_id'] ?? 0);
        $counterId = (int)($input['counter_id'] ?? 0);
        $device = DB::selectOne("SELECT d.id,d.device_name,p.id pairing_id FROM business_devices d INNER JOIN counter_pairing_requests p ON p.requested_device_id=d.id AND p.business_id=d.business_id AND p.status='pending' WHERE d.id=? AND d.business_id=? AND d.status='pending' LIMIT 1", [$deviceId,$businessId]);
        if (!$device) $this->fail('Pending device request not found.', 404);
        $counter = DB::selectOne('SELECT id,name FROM billing_counters WHERE id=? AND business_id=? AND active=1', [$counterId,$businessId]);
        if (!$counter) $this->fail('Choose an active billing counter.', 422);
        DB::statement("UPDATE business_devices SET counter_id=?,approved_by=?,approved_at=NOW(),updated_at=NOW() WHERE id=? AND business_id=?", [$counterId,$this->userId(),$deviceId,$businessId]);
        DB::statement("UPDATE counter_pairing_requests SET status='approved',approved_by=?,approved_at=NOW(),updated_at=NOW() WHERE id=?", [$this->userId(),$device->pairing_id]);
        $this->event($businessId,$deviceId,'pairing_approved',['counter_id'=>$counterId]);
        return $this->success(null, $device->device_name.' approved for '.$counter->name.'.');
    }

    public function rejectPairing(array $input): array
    {
        $businessId = $this->ownerAccess();
        $deviceId = (int)($input['device_id'] ?? 0);
        $device = DB::selectOne("SELECT d.id,p.id pairing_id FROM business_devices d INNER JOIN counter_pairing_requests p ON p.requested_device_id=d.id AND p.business_id=d.business_id AND p.status='pending' WHERE d.id=? AND d.business_id=? LIMIT 1", [$deviceId,$businessId]);
        if (!$device) $this->fail('Pending device request not found.',404);
        DB::statement("UPDATE counter_pairing_requests SET status='rejected',updated_at=NOW() WHERE id=?",[$device->pairing_id]);
        DB::statement("UPDATE business_devices SET status='inactive',updated_at=NOW() WHERE id=? AND business_id=?",[$deviceId,$businessId]);
        $this->event($businessId,$deviceId,'pairing_rejected');
        return $this->success(null,'Device request rejected.');
    }

    public function pairingStatus(array $input): array
    {
        $this->mainPcOnly();
        $this->validate(['request_id'=>'required|string','request_secret'=>'required|string']);
        $request = DB::selectOne('SELECT * FROM counter_pairing_requests WHERE request_uuid=? LIMIT 1 FOR UPDATE',[trim($input['request_id'])]);
        if (!$request || !$request->request_secret_hash || !hash_equals($request->request_secret_hash,hash('sha256',(string)$input['request_secret']))) $this->fail('Pairing request not found.',404);
        if (in_array($request->status,['open','pending'],true) && strtotime($request->expires_at)<time()) {
            DB::statement("UPDATE counter_pairing_requests SET status='expired' WHERE id=?",[$request->id]);
            return $this->success(['status'=>'expired']);
        }
        if ($request->status !== 'approved') return $this->success(['status'=>$request->status]);
        $device = DB::selectOne('SELECT id,device_uuid,counter_id FROM business_devices WHERE id=? AND business_id=? LIMIT 1',[$request->requested_device_id,$request->business_id]);
        if (!$device || !$device->counter_id) $this->fail('Approved counter assignment is missing.',409);
        $credential = bin2hex(random_bytes(32));
        DB::statement("UPDATE business_devices SET credential_hash=?,status='active',last_seen_at=NOW(),updated_at=NOW() WHERE id=?",[hash('sha256',$credential),$device->id]);
        DB::statement("UPDATE counter_pairing_requests SET status='consumed',consumed_at=NOW(),updated_at=NOW() WHERE id=?",[$request->id]);
        $counter = DB::selectOne('SELECT id,code,name,location_id FROM billing_counters WHERE id=? AND business_id=?',[$device->counter_id,$request->business_id]);
        $this->event((int)$request->business_id,(int)$device->id,'pairing_consumed',['counter_id'=>(int)$device->counter_id]);
        return $this->success(['status'=>'active','business_id'=>(int)$request->business_id,'device_uuid'=>$device->device_uuid,'device_credential'=>$credential,'counter'=>$counter]);
    }

    public function heartbeat(array $input): array
    {
        $this->mainPcOnly();
        $this->validate(['device_uuid'=>'required|string','device_credential'=>'required|string']);
        $device = DB::selectOne("SELECT d.id,d.business_id,d.status,d.credential_hash,d.app_version,c.id counter_id,c.code counter_code,c.name counter_name,c.location_id FROM business_devices d LEFT JOIN billing_counters c ON c.id=d.counter_id AND c.business_id=d.business_id WHERE d.device_uuid=? LIMIT 1",[strtolower(trim($input['device_uuid']))]);
        if (!$device || !$device->credential_hash || !hash_equals($device->credential_hash,hash('sha256',(string)$input['device_credential']))) $this->fail('Device authentication failed.',401);
        if ($device->status !== 'active') $this->fail('This billing device is not active.',403);
        DB::statement('UPDATE business_devices SET last_seen_at=NOW(),app_version=COALESCE(?,app_version),updated_at=NOW() WHERE id=?',[substr(trim($input['app_version'] ?? ''),0,40) ?: null,$device->id]);
        DB::statement('DELETE FROM device_sessions WHERE expires_at<=NOW()');
        $session = bin2hex(random_bytes(32));
        DB::statement('INSERT INTO device_sessions (device_id,token_hash,expires_at,last_seen_at) VALUES (?,?,DATE_ADD(NOW(),INTERVAL 12 HOUR),NOW())', [$device->id,hash('sha256',$session)]);
        return $this->success(['status'=>'active','business_id'=>(int)$device->business_id,'device_session'=>$session,'device_session_expires_in'=>43200,'counter'=>['id'=>(int)$device->counter_id,'code'=>$device->counter_code,'name'=>$device->counter_name,'location_id'=>$device->location_id ? (int)$device->location_id : null],'server_time'=>date(DATE_ATOM)]);
    }
}

