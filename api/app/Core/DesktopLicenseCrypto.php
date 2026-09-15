<?php

namespace App\Core;

use RuntimeException;

final class DesktopLicenseCrypto
{
    private string $directory;
    private string $privateKey;
    private string $publicKey;
    private string $encryptionKey;
    private string $hmacKey;

    public function __construct()
    {
        if (($_ENV['DESKTOP_MODE'] ?? '') === 'true') throw new RuntimeException('Cloud licensing service is unavailable locally.', 403);
        $storage = $_ENV['STORAGE_PATH'] ?? dirname(__DIR__, 2) . '/storage';
        $this->directory = rtrim($storage, '/\\') . '/licensing-cloud';
        $this->privateKey = $this->directory . '/signing-private.pem';
        $this->publicKey = $this->directory . '/signing-public.pem';
        $this->encryptionKey = $this->directory . '/encryption.key';
        $this->hmacKey = $this->directory . '/device-hmac.key';
        $this->ensureKeys();
    }

    private function ensureKeys(): void
    {
        if (!is_dir($this->directory) && !mkdir($this->directory, 0700, true) && !is_dir($this->directory)) throw new RuntimeException('Cannot create licensing key directory.');
        $lock = fopen($this->directory . '/key-generation.lock', 'c+');
        if (!$lock || !flock($lock, LOCK_EX)) throw new RuntimeException('Cannot lock licensing key storage.');
        try {
            if (!is_file($this->privateKey) || !is_file($this->publicKey)) {
                $key = openssl_pkey_new(['private_key_bits' => 3072, 'private_key_type' => OPENSSL_KEYTYPE_RSA]);
                if (!$key || !openssl_pkey_export($key, $private)) throw new RuntimeException('Cannot generate licence signing key.');
                $details = openssl_pkey_get_details($key);
                if (!$details || empty($details['key'])) throw new RuntimeException('Cannot export licence public key.');
                $this->atomicWrite($this->privateKey, $private);
                $this->atomicWrite($this->publicKey, $details['key']);
            }
            foreach ([$this->encryptionKey, $this->hmacKey] as $path) {
                if (!is_file($path)) $this->atomicWrite($path, base64_encode(random_bytes(32)));
            }
        } finally { flock($lock, LOCK_UN); fclose($lock); }
    }

    private function atomicWrite(string $path, string $contents): void
    {
        $temporary = $path . '.' . bin2hex(random_bytes(6)) . '.tmp';
        if (file_put_contents($temporary, $contents, LOCK_EX) === false) throw new RuntimeException('Cannot write licensing key material.');
        @chmod($temporary, 0600);
        if (!rename($temporary, $path)) { @unlink($temporary); throw new RuntimeException('Cannot publish licensing key material.'); }
    }

    private function key(string $path): string
    {
        $decoded = base64_decode(trim((string)file_get_contents($path)), true);
        if ($decoded === false || strlen($decoded) !== 32) throw new RuntimeException('Invalid licensing key material.');
        return $decoded;
    }

    public function encrypt(array $value): string
    {
        $nonce = random_bytes(12); $tag = '';
        $plain = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $cipher = openssl_encrypt($plain, 'aes-256-gcm', $this->key($this->encryptionKey), OPENSSL_RAW_DATA, $nonce, $tag);
        if ($cipher === false) throw new RuntimeException('Cannot encrypt licensing data.');
        return base64_encode(json_encode(['v'=>1,'n'=>base64_encode($nonce),'t'=>base64_encode($tag),'c'=>base64_encode($cipher)], JSON_THROW_ON_ERROR));
    }

    public function decrypt(string $envelope): array
    {
        $decoded = json_decode((string)base64_decode($envelope, true), true, 512, JSON_THROW_ON_ERROR);
        if (($decoded['v'] ?? null) !== 1) throw new RuntimeException('Unsupported encrypted licensing data.');
        $plain = openssl_decrypt(base64_decode($decoded['c'], true), 'aes-256-gcm', $this->key($this->encryptionKey), OPENSSL_RAW_DATA, base64_decode($decoded['n'], true), base64_decode($decoded['t'], true));
        if ($plain === false) throw new RuntimeException('Licensing data authentication failed.');
        return json_decode($plain, true, 512, JSON_THROW_ON_ERROR);
    }

    public function deviceHmac(string $deviceId): string
    {
        return hash_hmac('sha256', strtolower(trim($deviceId)), $this->key($this->hmacKey));
    }

    public function publicKeyXml(): string
    {
        $key = openssl_pkey_get_public((string)file_get_contents($this->publicKey));
        $details = $key ? openssl_pkey_get_details($key) : false;
        if (!$details || empty($details['rsa'])) throw new RuntimeException('Invalid licence public key.');
        return '<RSAKeyValue><Modulus>' . base64_encode($details['rsa']['n']) . '</Modulus><Exponent>' . base64_encode($details['rsa']['e']) . '</Exponent></RSAKeyValue>';
    }

    public function sign(array $claims): string
    {
        $payload = json_encode($claims, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        $key = openssl_pkey_get_private((string)file_get_contents($this->privateKey));
        if (!$key || !openssl_sign($payload, $signature, $key, OPENSSL_ALGO_SHA256)) throw new RuntimeException('Cannot sign desktop licence.');
        return json_encode(['payload'=>$this->base64Url($payload),'signature'=>$this->base64Url($signature)], JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
    }

    private function base64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}

