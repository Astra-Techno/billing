<?php

namespace App\Core;

final class DesktopLicenseLocal
{
    private static function executable(): string { return dirname(__DIR__,3).'/desktop/LicenseHost.exe'; }
    public static function run(array $arguments, bool $allowMissing=false): array {
        $command=array_merge([self::executable()],$arguments);$pipes=[];$process=proc_open($command,[1=>['pipe','w'],2=>['pipe','w']],$pipes,null,null,['bypass_shell'=>true,'create_process_group'=>true,'create_new_console'=>false]);
        if(!is_resource($process))throw new \RuntimeException('Licence verification service could not start.');$out=stream_get_contents($pipes[1]);$err=stream_get_contents($pipes[2]);fclose($pipes[1]);fclose($pipes[2]);$code=proc_close($process);
        if($code!==0&&!($allowMissing&&$code===10))throw new \RuntimeException($err?:'Licence verification failed.',402);return ['code'=>$code,'output'=>$out];
    }
    public static function status(): array { try{$result=self::run(['verify',DesktopBackup::home()],true);if($result['code']===10)return ['active'=>false,'reason'=>'not_activated'];return json_decode($result['output'],true,512,JSON_THROW_ON_ERROR);}catch(\Throwable $e){return ['active'=>false,'reason'=>'invalid','message'=>$e->getMessage()];} }
    public static function requireActive(): void { $status=self::status();if(empty($status['active']))throw new \RuntimeException('This PC must be activated.',402); }
}
