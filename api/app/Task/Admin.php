<?php

namespace App\Task;

use App\Base\Task;
use App\Core\Auth;
use App\Core\DB;
use App\Core\DesktopLicenseCrypto;

class Admin extends Task
{
    protected array $rules = [];

    // ── Guard: only super admins may call these ───────────────────────────────

    private function requireSuperAdmin(): void
    {
        $user = Auth::user();
        if (!$user || !(int)($user->is_super_admin ?? 0)) {
            $this->fail('Super admin access required.', 403);
        }
    }

    // ── Platform stats ────────────────────────────────────────────────────────

    public function stats(array $input): array
    {
        $this->requireSuperAdmin();
        $data = $this->sql('Admin.stats')->object();
        return $this->success($data);
    }

    // ── Businesses list ───────────────────────────────────────────────────────

    public function businesses(array $input): array
    {
        $this->requireSuperAdmin();
        $rows = $this->sql('Admin.businesses', $input)->objectList();
        return $this->success($rows);
    }

    // ── Users list ────────────────────────────────────────────────────────────

    public function users(array $input): array
    {
        $this->requireSuperAdmin();
        $rows = $this->sql('Admin.users', $input)->objectList();
        return $this->success($rows);
    }

    // ── Suspend / activate a business ────────────────────────────────────────

    public function toggleBusiness(array $input): array
    {
        $this->requireSuperAdmin();
        $this->validate(['business_id' => 'required|integer']);

        $id   = (int)$input['business_id'];
        $biz  = DB::selectOne("SELECT id, active FROM businesses WHERE id = ? LIMIT 1", [$id]);
        if (!$biz) $this->fail('Business not found.', 404);

        $newActive = $biz->active ? 0 : 1;
        DB::statement("UPDATE businesses SET active = ? WHERE id = ?", [$newActive, $id]);

        $label = $newActive ? 'activated' : 'suspended';
        return $this->success(['active' => $newActive], "Business {$label}.");
    }

    // ── Suspend / activate a user ─────────────────────────────────────────────

    public function toggleUser(array $input): array
    {
        $this->requireSuperAdmin();
        $this->validate(['user_id' => 'required|integer']);

        $id   = (int)$input['user_id'];
        $user = DB::selectOne("SELECT id, active FROM users WHERE id = ? LIMIT 1", [$id]);
        if (!$user) $this->fail('User not found.', 404);

        // Prevent locking out the calling super admin
        if ($id === Auth::id()) $this->fail('Cannot deactivate your own account.', 400);

        $newActive = $user->active ? 0 : 1;
        DB::statement("UPDATE users SET active = ? WHERE id = ?", [$newActive, $id]);

        $label = $newActive ? 'activated' : 'suspended';
        return $this->success(['active' => $newActive], "User {$label}.");
    }

    public function desktopLicenseStats(array $input): array
    {
        $this->requireSuperAdmin();
        return $this->success((array)DB::selectOne("SELECT
            (SELECT COUNT(*) FROM desktop_activation_requests WHERE status='pending' AND expires_at>NOW()) pending,
            (SELECT COUNT(*) FROM desktop_licenses WHERE status='active') active,
            (SELECT COUNT(*) FROM desktop_licenses WHERE status='suspended') suspended,
            (SELECT COUNT(*) FROM desktop_licenses WHERE status='revoked') revoked,
            (SELECT COUNT(*) FROM desktop_licenses WHERE status='active' AND expires_at BETWEEN NOW() AND DATE_ADD(NOW(),INTERVAL 30 DAY)) expiring"));
    }

    public function desktopActivationRequests(array $input): array
    {
        $this->requireSuperAdmin(); $crypto = new DesktopLicenseCrypto();
        DB::statement("UPDATE desktop_activation_requests SET status='expired' WHERE status='pending' AND expires_at<NOW()");
        $rows = DB::select("SELECT r.*,u.name cloud_user_name,b.name cloud_business_name,a.name approved_by_name FROM desktop_activation_requests r LEFT JOIN users u ON u.id=r.user_id LEFT JOIN businesses b ON b.id=r.business_id LEFT JOIN users a ON a.id=r.approved_by ORDER BY (r.status='pending') DESC,r.created_at DESC LIMIT 250");
        return $this->success(array_map(function($row) use ($crypto) { $payload=$crypto->decrypt($row->encrypted_payload); unset($row->encrypted_payload,$row->request_secret_hash); $row->user=$payload['user']??[]; $row->company=$payload['company']??[]; $row->device=$payload['device']??[]; $row->device_id=substr($payload['device_id']??'',0,12).'…'; return $row; },$rows));
    }

    public function desktopLicenses(array $input): array
    {
        $this->requireSuperAdmin(); $crypto = new DesktopLicenseCrypto();
        $rows=DB::select("SELECT l.*,a.name approved_by_name,r.name revoked_by_name FROM desktop_licenses l LEFT JOIN users a ON a.id=l.approved_by LEFT JOIN users r ON r.id=l.revoked_by ORDER BY l.created_at DESC LIMIT 500");
        return $this->success(array_map(function($row) use($crypto) { $row->customer=$crypto->decrypt($row->encrypted_customer); $row->device=$crypto->decrypt($row->encrypted_device); unset($row->encrypted_customer,$row->encrypted_device,$row->license_document,$row->device_hmac); if(isset($row->device['device_id'])) $row->device['device_id']=substr($row->device['device_id'],0,12).'…'; return $row; },$rows));
    }

    public function approveDesktopActivation(array $input): array
    {
        $this->requireSuperAdmin(); $this->validate(['request_id'=>'required|integer','years'=>'required|integer']);
        $years=(int)($input['years']??0); if($years<1||$years>10) $this->fail('Licence validity must be between 1 and 10 years.',422);
        return DB::transaction(function() use($input,$years) {
            $request=DB::selectOne('SELECT * FROM desktop_activation_requests WHERE id=? FOR UPDATE',[(int)$input['request_id']]);
            if(!$request) $this->fail('Activation request not found.',404);
            if($request->status!=='pending') $this->fail('Only pending activation requests can be approved.',422);
            if(strtotime($request->expires_at)<time()) { DB::statement("UPDATE desktop_activation_requests SET status='expired' WHERE id=?",[$request->id]); $this->fail('Activation request expired. Ask the PC to retry.',422); }
            $crypto=new DesktopLicenseCrypto(); $payload=$crypto->decrypt($request->encrypted_payload); $uuid=$this->licenseUuid();
            $expires=date('Y-m-d 23:59:59',strtotime("+{$years} years"));
            $claims=['license_id'=>$uuid,'device_id'=>$payload['device_id'],'customer'=>$payload['user']??[],'company'=>$payload['company']??[],'edition'=>$input['edition']??'offline-single-pc','status'=>'active','issued_at'=>time(),'expires_at'=>strtotime($expires),'max_version'=>$input['max_version']??null];
            $document=$crypto->sign($claims);
            DB::statement("INSERT INTO desktop_licenses (license_uuid,activation_request_id,user_id,business_id,device_hmac,encrypted_customer,encrypted_device,status,edition,issued_at,expires_at,max_version,license_document_hash,license_document,approved_by,created_at,updated_at) VALUES (?,?,?,?,?,?,?,'active',?,NOW(),?,?,?, ?,?,NOW(),NOW())",[
                $uuid,$request->id,$request->user_id,$request->business_id,$request->device_hmac,$crypto->encrypt(['user'=>$payload['user']??[],'company'=>$payload['company']??[]]),$crypto->encrypt(['device'=>$payload['device']??[],'device_id'=>$payload['device_id'],'app_version'=>$payload['app_version']??null]),$claims['edition'],$expires,$claims['max_version'],hash('sha256',$document),$document,\App\Core\Auth::id()
            ]);
            $licenseId=(int)DB::lastInsertId(); DB::statement("UPDATE desktop_activation_requests SET status='approved',approved_by=?,approved_at=NOW() WHERE id=?",[\App\Core\Auth::id(),$request->id]);
            $this->licenseEvent($licenseId,(int)$request->id,'approved',['edition'=>$claims['edition'],'expires_at'=>$expires]);
            return $this->success(['license_id'=>$uuid],'Desktop activation approved.');
        });
    }

    public function rejectDesktopActivation(array $input): array
    {
        $this->requireSuperAdmin(); $this->validate(['request_id'=>'required|integer','reason'=>'required|string']);
        $changed=DB::affectingStatement("UPDATE desktop_activation_requests SET status='rejected',rejected_at=NOW(),rejection_reason=? WHERE id=? AND status='pending'",[trim($input['reason']),(int)$input['request_id']]);
        if(!$changed) $this->fail('Pending activation request not found.',404);
        $this->licenseEvent(null,(int)$input['request_id'],'rejected',['reason'=>trim($input['reason'])]);
        return $this->success(null,'Activation request rejected.');
    }

    public function setDesktopLicenseStatus(array $input): array
    {
        $this->requireSuperAdmin(); $this->validate(['license_id'=>'required|integer','status'=>'required|in:active,suspended,revoked']);
        return DB::transaction(function() use($input) {
            $row=DB::selectOne('SELECT * FROM desktop_licenses WHERE id=? FOR UPDATE',[(int)$input['license_id']]); if(!$row)$this->fail('Desktop licence not found.',404);
            $reason=trim((string)($input['reason']??'')); if($input['status']!=='active' && $reason==='')$this->fail('A reason is required.',422);
            $crypto=new DesktopLicenseCrypto(); $customer=$crypto->decrypt($row->encrypted_customer); $device=$crypto->decrypt($row->encrypted_device);
            $claims=['license_id'=>$row->license_uuid,'device_id'=>$device['device_id'],'customer'=>$customer['user']??[],'company'=>$customer['company']??[],'edition'=>$row->edition,'status'=>$input['status'],'issued_at'=>strtotime($row->issued_at),'expires_at'=>$row->expires_at?strtotime($row->expires_at):null,'max_version'=>$row->max_version];
            $document=$crypto->sign($claims); $revoked=$input['status']==='revoked';
            DB::statement('UPDATE desktop_licenses SET status=?,license_document=?,license_document_hash=?,revoked_by=?,revoked_at=?,revocation_reason=?,updated_at=NOW() WHERE id=?',[$input['status'],$document,hash('sha256',$document),$revoked?\App\Core\Auth::id():null,$revoked?date('Y-m-d H:i:s'):null,$revoked?$reason:null,$row->id]);
            $this->licenseEvent((int)$row->id,null,$input['status'],['reason'=>$reason]); return $this->success(['status'=>$input['status']],'Desktop licence updated.');
        });
    }

    private function licenseUuid(): string { $b=random_bytes(16);$b[6]=chr((ord($b[6])&15)|64);$b[8]=chr((ord($b[8])&63)|128);return vsprintf('%s%s-%s-%s-%s-%s%s%s',str_split(bin2hex($b),4)); }
    private function licenseEvent(?int $licenseId,?int $requestId,string $action,array $metadata): void { $crypto=new DesktopLicenseCrypto(); DB::statement('INSERT INTO desktop_license_events (license_id,activation_request_id,action,encrypted_metadata,actor_admin_id,ip_address,user_agent,created_at) VALUES (?,?,?,?,?,?,?,NOW())',[$licenseId,$requestId,$action,$crypto->encrypt($metadata),\App\Core\Auth::id(),$_SERVER['REMOTE_ADDR']??null,substr($_SERVER['HTTP_USER_AGENT']??'',0,500)]); }
}
