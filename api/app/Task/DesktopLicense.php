<?php

namespace App\Task;

use App\Base\Task;
use App\Core\DB;
use App\Core\DesktopLicenseCrypto;
use App\Core\DesktopLicenseLocal;
use App\Core\DesktopBackup;

class DesktopLicense extends Task
{
    private function localOnly(): void { if(($_ENV['DESKTOP_MODE']??'')!=='true')$this->fail('Local activation action is unavailable in the cloud.',403); }
    private function localPost(string $method,array $payload): array {
        $home=DesktopBackup::home();$input=$home.'/cache/license-request-'.bin2hex(random_bytes(5)).'.json';
        if(!is_dir(dirname($input)))mkdir(dirname($input),0700,true);file_put_contents($input,json_encode($payload,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),LOCK_EX);
        try{$result=DesktopLicenseLocal::run(['post','https://billing.cloudkart24.com/api/desktop-license/'.$method,$input]);return json_decode($result['output'],true,512,JSON_THROW_ON_ERROR);}finally{@unlink($input);}
    }
    public function localStatus(array $input): array { $this->localOnly();return $this->success(DesktopLicenseLocal::status()); }
    public function localRequest(array $input): array {
        $this->localOnly();$businessId=$this->requireBusiness();$this->requireRole(['owner','admin']);$home=DesktopBackup::home();
        $type = in_array($input['type'] ?? '', ['trial','activation'], true) ? $input['type'] : 'activation';
        $requestedYears = $type === 'activation' && !empty($input['requested_years']) ? max(1, min(5, (int)$input['requested_years'])) : null;
        $deviceResult=DesktopLicenseLocal::run(['device']);$device=json_decode($deviceResult['output'],true,512,JSON_THROW_ON_ERROR);
        $user=DB::selectOne('SELECT name,email,mobile FROM users WHERE id=?',[$this->userId()]);$company=DB::selectOne('SELECT name,email,mobile,gstin,business_type FROM businesses WHERE id=?',[$businessId]);
        $payload = ['device_id'=>$device['device_id'],'device'=>$device,'user'=>(array)$user,'company'=>(array)$company,'app_version'=>'1.0.0','type'=>$type];
        if ($requestedYears) $payload['requested_years'] = $requestedYears;
        $response=$this->localPost('request',$payload);
        if(empty($response['success']))$this->fail($response['message']??'Cloud activation request failed.',502);$request=$response['data'];
        $plain=$home.'/cache/activation-request.json';file_put_contents($plain,json_encode($request,JSON_THROW_ON_ERROR),LOCK_EX);try{DesktopLicenseLocal::run(['protect',$plain,$home.'/activation-request.dat']);}finally{@unlink($plain);}
        return $this->success(['status'=>$type === 'trial' ? 'trial_started' : 'pending','type'=>$type,'expires_in'=>$request['expires_in']??600]);
    }
    public function localPoll(array $input): array {
        $this->localOnly();$this->requireBusiness();$home=DesktopBackup::home();$protected=$home.'/activation-request.dat';
        $saved=DesktopLicenseLocal::run(['unprotect',$protected],true);if($saved['code']===10)return $this->success(['status'=>'not_requested']);$request=json_decode($saved['output'],true,512,JSON_THROW_ON_ERROR);
        $response=$this->localPost('status',['request_id'=>$request['request_id'],'request_secret'=>$request['request_secret']]);if(empty($response['success']))$this->fail($response['message']??'Activation status failed.',502);$data=$response['data'];
        if(!empty($data['license_document'])){$temp=$home.'/cache/activation-response.json';file_put_contents($temp,json_encode(['data'=>$data],JSON_THROW_ON_ERROR),LOCK_EX);try{DesktopLicenseLocal::run(['install',$temp,$home]);}finally{@unlink($temp);}@unlink($protected);return $this->success(DesktopLicenseLocal::status(),'This PC is activated.');}
        return $this->success(['status'=>$data['status']??'pending']);
    }
    public function localRefresh(array $input): array {
        $this->localOnly();$this->requireBusiness();$home=DesktopBackup::home();$local=DesktopLicenseLocal::status();if(empty($local['active']))return $this->success($local);
        try{$response=$this->localPost('refresh',['license_id'=>$local['license_id'],'device_id'=>$local['device']['device_id']]);if(empty($response['success']))return $this->success($local);$temp=$home.'/cache/license-refresh.json';file_put_contents($temp,json_encode($response,JSON_THROW_ON_ERROR),LOCK_EX);try{DesktopLicenseLocal::run(['install',$temp,$home]);}finally{@unlink($temp);}return $this->success(DesktopLicenseLocal::status());}catch(\Throwable){return $this->success($local,'Offline licence remains valid.');}
    }
    private function licenseEvent2(?int $licenseId, ?int $requestId, string $action, array $metadata): void
    {
        $crypto = new DesktopLicenseCrypto();
        DB::statement('INSERT INTO desktop_license_events (license_id,activation_request_id,action,encrypted_metadata,ip_address,user_agent,created_at) VALUES (?,?,?,?,?,?,NOW())', [
            $licenseId, $requestId, $action, $crypto->encrypt($metadata), $_SERVER['REMOTE_ADDR'] ?? null, substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 500)
        ]);
    }

    private function cloudOnly(): void
    {
        if (($_ENV['DESKTOP_MODE'] ?? '') === 'true') $this->fail('Cloud activation endpoint is unavailable locally.', 403);
    }

    private function uuid(): string
    {
        $b = random_bytes(16); $b[6] = chr((ord($b[6]) & 0x0f) | 0x40); $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }

    public function publicKey(array $input): array
    {
        $this->cloudOnly();
        return $this->success(['public_key' => (new DesktopLicenseCrypto())->publicKeyXml()]);
    }

    public function request(array $input): array
    {
        $this->cloudOnly();
        $this->validate(['device_id'=>'required|string|min_length:32', 'user'=>'required|array', 'company'=>'required|array', 'device'=>'required|array']);
        $type = in_array($input['type'] ?? '', ['trial','activation'], true) ? $input['type'] : 'activation';
        $requestedYears = $type === 'activation' && !empty($input['requested_years']) ? max(1, min(5, (int)$input['requested_years'])) : null;
        $crypto = new DesktopLicenseCrypto();
        $requestUuid = $this->uuid(); $secret = bin2hex(random_bytes(32));
        $email = strtolower(trim((string)($input['user']['email'] ?? '')));
        $user = $email ? DB::selectOne('SELECT id FROM users WHERE email = ? LIMIT 1', [$email]) : null;
        $business = $user ? DB::selectOne('SELECT b.id FROM businesses b INNER JOIN business_users bu ON bu.business_id=b.id WHERE bu.user_id=? AND LOWER(b.name)=LOWER(?) LIMIT 1', [$user->id, trim((string)($input['company']['name'] ?? ''))]) : null;
        $deviceHmac = $crypto->deviceHmac($input['device_id']);
        $recent=DB::selectOne('SELECT COUNT(*) total FROM desktop_activation_requests WHERE device_hmac=? AND created_at>DATE_SUB(NOW(),INTERVAL 1 HOUR)',[$deviceHmac]);
        if((int)($recent->total??0)>=5)$this->fail('Too many activation requests. Try again later.',429);

        // Only one trial per device
        if ($type === 'trial') {
            $existingTrial = DB::selectOne("SELECT id FROM desktop_licenses WHERE device_hmac=? AND license_type='trial'", [$deviceHmac]);
            if ($existingTrial) $this->fail('A free trial has already been used on this device. Please request a full licence.', 422);
        }

        DB::statement("UPDATE desktop_activation_requests SET status='expired' WHERE device_hmac=? AND status='pending'", [$deviceHmac]);
        DB::statement("INSERT INTO desktop_activation_requests (request_uuid,request_secret_hash,user_id,business_id,device_hmac,type,requested_years,encrypted_payload,status,expires_at,created_at,updated_at) VALUES (?,?,?,?,?,?,?,?,?,DATE_ADD(NOW(),INTERVAL 10 MINUTE),NOW(),NOW())", [
            $requestUuid, hash('sha256',$secret), $user?->id, $business?->id, $deviceHmac, $type, $requestedYears,
            $crypto->encrypt(['user'=>$input['user'],'company'=>$input['company'],'device'=>$input['device'],'device_id'=>$input['device_id'],'app_version'=>$input['app_version'] ?? null]),
            $type === 'trial' ? 'approved' : 'pending'
        ]);
        $requestId = (int)DB::lastInsertId();
        DB::statement("INSERT INTO desktop_license_events (activation_request_id,action,encrypted_metadata,ip_address,user_agent,created_at) VALUES (?,'requested',?,?,?,NOW())", [$requestId,$crypto->encrypt(['source'=>'desktop','type'=>$type]),$_SERVER['REMOTE_ADDR'] ?? null,substr($_SERVER['HTTP_USER_AGENT'] ?? '',0,500)]);

        // Auto-approve trial: create 30-day licence immediately
        if ($type === 'trial') {
            $payload = ['user'=>$input['user'],'company'=>$input['company'],'device'=>$input['device'],'device_id'=>$input['device_id']];
            $licenseUuid = $this->uuid();
            $expires = date('Y-m-d 23:59:59', strtotime('+30 days'));
            $claims = ['license_id'=>$licenseUuid,'device_id'=>$input['device_id'],'customer'=>$input['user'],'company'=>$input['company'],'edition'=>'trial','status'=>'active','issued_at'=>time(),'expires_at'=>strtotime($expires),'max_version'=>null];
            $document = $crypto->sign($claims);
            DB::statement("INSERT INTO desktop_licenses (license_uuid,activation_request_id,user_id,business_id,device_hmac,encrypted_customer,encrypted_device,status,edition,license_type,issued_at,expires_at,license_document_hash,license_document,approved_by,created_at,updated_at) VALUES (?,?,?,?,?,?,?,'active','trial','trial',NOW(),?,?,?,NULL,NOW(),NOW())", [
                $licenseUuid, $requestId, $user?->id, $business?->id, $deviceHmac,
                $crypto->encrypt(['user'=>$input['user'],'company'=>$input['company']]),
                $crypto->encrypt(['device'=>$input['device'],'device_id'=>$input['device_id'],'app_version'=>$input['app_version']??null]),
                $expires, hash('sha256',$document), $document
            ]);
            $licenseId = (int)DB::lastInsertId();
            DB::statement("UPDATE desktop_activation_requests SET status='consumed',approved_at=NOW(),consumed_at=NOW() WHERE id=?", [$requestId]);
            $this->licenseEvent2($licenseId, $requestId, 'trial_started', ['edition'=>'trial','expires_at'=>$expires]);

            // Send admin notification
            $adminIds = DB::select("SELECT id FROM users WHERE is_super_admin = 1 AND active = 1");
            foreach ($adminIds as $admin) {
                DB::statement("INSERT INTO notifications (business_id,user_id,type,title,message,data,created_at) VALUES (NULL,?,?,?,?,?,NOW())", [
                    $admin->id, 'desktop_trial',
                    'New trial started: ' . trim($input['company']['name'] ?? $input['user']['name'] ?? 'Unknown'),
                    trim($input['user']['name'] ?? '') . ' (' . ($email ?: 'no email') . ') started a 30-day free trial.',
                    json_encode(['request_id'=>$requestId,'device_hmac'=>substr($deviceHmac,0,12)])
                ]);
            }
        }

        return $this->success(['request_id'=>$requestUuid,'request_secret'=>$secret,'expires_in'=>600,'type'=>$type]);
    }

    public function status(array $input): array
    {
        $this->cloudOnly();
        $this->validate(['request_id'=>'required|string','request_secret'=>'required|string']);
        $request = DB::selectOne('SELECT * FROM desktop_activation_requests WHERE request_uuid=? LIMIT 1', [$input['request_id']]);
        if (!$request || !hash_equals($request->request_secret_hash, hash('sha256',$input['request_secret']))) $this->fail('Activation request not found.',404);
        if ($request->status === 'pending' && strtotime($request->expires_at) < time()) { DB::statement("UPDATE desktop_activation_requests SET status='expired' WHERE id=?",[$request->id]); $request->status='expired'; }
        $data = ['status'=>$request->status];
        if (in_array($request->status,['approved','consumed'],true)) {
            $license = DB::selectOne('SELECT license_uuid,status,license_document FROM desktop_licenses WHERE activation_request_id=? LIMIT 1',[$request->id]);
            if ($license) $data += ['license_id'=>$license->license_uuid,'license_status'=>$license->status,'license_document'=>$license->license_document,'public_key'=>(new DesktopLicenseCrypto())->publicKeyXml()];
            if ($request->status === 'approved') DB::statement("UPDATE desktop_activation_requests SET status='consumed',consumed_at=NOW() WHERE id=?",[$request->id]);
        }
        return $this->success($data);
    }

    public function refresh(array $input): array
    {
        $this->cloudOnly();
        $this->validate(['license_id'=>'required|string','device_id'=>'required|string']);
        $crypto = new DesktopLicenseCrypto();
        $license = DB::selectOne('SELECT license_uuid,status,license_document FROM desktop_licenses WHERE license_uuid=? AND device_hmac=? LIMIT 1',[$input['license_id'],$crypto->deviceHmac($input['device_id'])]);
        if (!$license) $this->fail('Licence not found.',404);
        DB::statement('UPDATE desktop_licenses SET last_cloud_contact_at=NOW() WHERE license_uuid=?',[$license->license_uuid]);
        return $this->success(['license_id'=>$license->license_uuid,'status'=>$license->status,'license_document'=>$license->license_document,'public_key'=>$crypto->publicKeyXml()]);
    }
}
