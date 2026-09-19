<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\DB;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class InvoiceThermalController
{
    public function serialPrint(Request $request, Response $response, array $args): Response
    {
        if (($_ENV['DESKTOP_MODE'] ?? '') !== 'true') return $this->error($response, 404, 'Desktop printing only');
        $businessId = Auth::businessId();
        $invoiceId = (int)($args['id'] ?? 0);
        $invoice = $businessId ? $this->invoice($invoiceId, $businessId) : null;
        if (!$invoice) return $this->error($response, 404, 'Invoice not found');
        $port = strtoupper((string)(($request->getParsedBody() ?? [])['port'] ?? ''));
        if ($port !== '' && !preg_match('/^COM(?:[1-9]|[1-9][0-9])$/D', $port)) return $this->error($response, 422, 'Select a valid Bluetooth COM port');

        $business = DB::selectOne('SELECT name, mobile, gstin, address_line1, address_line2, city, pincode, upi_id FROM businesses WHERE id = ? LIMIT 1', [$businessId]);
        $items = DB::select('SELECT description, quantity, unit, unit_price, total, gst_rate, hsn_sac FROM invoice_items WHERE invoice_id = ? ORDER BY sort_order ASC', [$invoiceId]);
        $lines = self::formatReceipt((array)$invoice, (array)($business ?? []), array_map(fn($item) => (array)$item, $items));
        $bytes = self::escPos($lines);
        $helper = dirname(__DIR__, 3) . '/desktop/ThermalPrintHost.exe';
        if (!is_file($helper)) return $this->error($response, 503, 'Bluetooth print helper is missing');
        $process = proc_open([$helper, $port ?: '--auto'], [0 => ['pipe', 'r'], 1 => ['pipe', 'w'], 2 => ['pipe', 'w']], $pipes, null, null, ['bypass_shell' => true]);
        if (!is_resource($process)) return $this->error($response, 503, 'Could not start Bluetooth print helper');
        fwrite($pipes[0], $bytes);
        fclose($pipes[0]);
        stream_get_contents($pipes[1]); fclose($pipes[1]);
        $error = trim(stream_get_contents($pipes[2])); fclose($pipes[2]);
        if (proc_close($process) !== 0) return $this->error($response, 503, $error ?: 'Printer did not accept the job');
        $response->getBody()->write(json_encode(['success' => true, 'message' => 'Receipt sent to PSF588']));
        return $response->withHeader('Content-Type', 'application/json')->withHeader('Cache-Control', 'no-store');
    }

    public function create(Request $request, Response $response, array $args): Response
    {
        $businessId = Auth::businessId();
        $invoiceId = (int)($args['id'] ?? 0);
        if (!$businessId || !$invoiceId || !$this->invoice($invoiceId, $businessId)) {
            return $this->error($response, 404, 'Invoice not found');
        }

        $token = bin2hex(random_bytes(32));
        DB::statement('DELETE FROM thermal_print_jobs WHERE expires_at < NOW()');
        DB::statement(
            'INSERT INTO thermal_print_jobs (token_hash, business_id, invoice_id, expires_at) VALUES (?, ?, ?, DATE_ADD(NOW(), INTERVAL 3 MINUTE))',
            [hash('sha256', $token), $businessId, $invoiceId]
        );
        $base = rtrim($_ENV['APP_URL'] ?? '', '/');
        if (!str_starts_with($base, 'https://') && ($_ENV['APP_ENV'] ?? '') !== 'local') {
            return $this->error($response, 503, 'Secure print URL is not configured');
        }
        $response->getBody()->write(json_encode(['success' => true, 'data' => [
            'url' => $base . '/invoice/bluetooth-print/' . $token,
            'expires_in' => 180,
        ]]));
        return $response->withHeader('Content-Type', 'application/json')->withHeader('Cache-Control', 'no-store');
    }

    public function show(Request $request, Response $response, array $args): Response
    {
        $token = (string)($args['token'] ?? '');
        if (!preg_match('/^[a-f0-9]{64}$/D', $token)) return $this->error($response, 404, 'Print link not found');
        $job = DB::selectOne(
            'SELECT business_id, invoice_id FROM thermal_print_jobs WHERE token_hash = ? AND expires_at > NOW() LIMIT 1',
            [hash('sha256', $token)]
        );
        if (!$job) return $this->error($response, 404, 'Print link expired');
        $invoice = $this->invoice((int)$job->invoice_id, (int)$job->business_id);
        if (!$invoice) return $this->error($response, 404, 'Invoice not found');
        $business = DB::selectOne('SELECT name, mobile, gstin, address_line1, address_line2, city, pincode, upi_id FROM businesses WHERE id = ? LIMIT 1', [(int)$job->business_id]);
        $items = DB::select('SELECT description, quantity, unit, unit_price, total, gst_rate, hsn_sac FROM invoice_items WHERE invoice_id = ? ORDER BY sort_order ASC', [(int)$job->invoice_id]);
        $lines = self::formatReceipt((array)$invoice, (array)($business ?? []), array_map(fn($item) => (array)$item, $items));
        $response->getBody()->write(json_encode($lines, JSON_FORCE_OBJECT | JSON_UNESCAPED_UNICODE | JSON_INVALID_UTF8_SUBSTITUTE));
        return $response->withHeader('Content-Type', 'application/json; charset=utf-8')
            ->withHeader('Cache-Control', 'no-store')->withHeader('Referrer-Policy', 'no-referrer')
            ->withHeader('X-Content-Type-Options', 'nosniff');
    }

    public static function formatReceipt(array $invoice, array $business, array $items): array
    {
        $lines = [];
        $add = static function (string $value, bool $bold = false, int $align = 0) use (&$lines): void {
            $lines[] = ['type' => 0, 'content' => $value, 'bold' => $bold ? 1 : 0, 'align' => $align, 'format' => 0];
        };
        $money = static fn($value): string => 'Rs ' . number_format((float)($value ?? 0), 2, '.', ',');
        $add((string)($business['name'] ?? 'AI Billing'), true, 1);
        $address = implode(', ', array_filter([$business['address_line1'] ?? '', $business['address_line2'] ?? '', $business['city'] ?? '', $business['pincode'] ?? '']));
        if ($address !== '') $add($address, false, 1);
        if (!empty($business['mobile'])) $add('Tel: ' . $business['mobile'], false, 1);
        if (!empty($business['gstin'])) $add('GSTIN: ' . $business['gstin'], false, 1);
        $add('--------------------------------');
        $type = ['tax_invoice' => 'TAX INVOICE', 'bill_of_supply' => 'BILL OF SUPPLY', 'retail' => 'RETAIL INVOICE', 'export' => 'EXPORT INVOICE', 'proforma' => 'PROFORMA INVOICE'];
        $add($type[$invoice['invoice_type'] ?? ''] ?? 'INVOICE', true, 1);
        $add('No: ' . ($invoice['number'] ?? ''));
        $add('Date: ' . ($invoice['issue_date'] ?? ''));
        $add('Bill to: ' . ($invoice['client_name'] ?? 'Walk-in Customer'));
        if (!empty($invoice['client_gstin'])) $add('Customer GSTIN: ' . $invoice['client_gstin']);
        $add('--------------------------------');
        foreach ($items as $item) {
            $add((string)($item['description'] ?? ''), true);
            if (!empty($item['hsn_sac'])) $add('HSN/SAC: ' . $item['hsn_sac']);
            $add(($item['quantity'] ?? 0) . ' ' . ($item['unit'] ?: 'Nos') . ' x ' . $money($item['unit_price'] ?? 0) . ' = ' . $money($item['total'] ?? 0));
            if ((float)($item['gst_rate'] ?? 0) > 0) $add('GST ' . $item['gst_rate'] . '%');
        }
        $add('--------------------------------');
        $add('Subtotal: ' . $money((float)($invoice['subtotal'] ?? 0) + (float)($invoice['discount'] ?? 0)));
        if ((float)($invoice['discount'] ?? 0) > 0) $add('Discount: -' . $money($invoice['discount']));
        foreach (['cgst_total' => 'CGST', 'sgst_total' => 'SGST', 'igst_total' => 'IGST', 'utgst_total' => 'UTGST'] as $field => $label) {
            if ((float)($invoice[$field] ?? 0) > 0) $add($label . ': ' . $money($invoice[$field]));
        }
        $add('TOTAL: ' . $money($invoice['total'] ?? 0), true);
        if ((float)($invoice['amount_paid'] ?? 0) > 0) $add('Paid: ' . $money($invoice['amount_paid']));
        if ((float)($invoice['amount_due'] ?? 0) > 0) $add('Balance due: ' . $money($invoice['amount_due']), true);
        if (!empty($business['upi_id'])) $add('UPI: ' . $business['upi_id']);
        if (!empty($invoice['notes'])) $add((string)$invoice['notes']);
        $add('Thank you!', true, 1);
        $add(' ');
        return $lines;
    }

    public static function escPos(array $lines): string
    {
        $bytes = "\x1B\x40";
        foreach ($lines as $line) {
            $align = in_array($line['align'] ?? 0, [0, 1, 2], true) ? $line['align'] : 0;
            $bold = !empty($line['bold']) ? 1 : 0;
            $text = preg_replace('/[\x00-\x1F\x7F]/', ' ', (string)($line['content'] ?? ''));
            $text = iconv('UTF-8', 'CP437//TRANSLIT//IGNORE', $text) ?: '';
            $bytes .= "\x1B\x61" . chr($align) . "\x1B\x45" . chr($bold) . substr($text, 0, 512) . "\n";
        }
        return $bytes . "\x1B\x45\x00\x1B\x61\x00\n\n";
    }

    private function invoice(int $invoiceId, int $businessId): ?object
    {
        return DB::selectOne('SELECT i.*, c.name AS client_name, c.gstin AS client_gstin FROM invoices i LEFT JOIN clients c ON c.id = i.client_id WHERE i.id = ? AND i.business_id = ? AND i.deleted_at IS NULL LIMIT 1', [$invoiceId, $businessId]);
    }

    private function error(Response $response, int $status, string $message): Response
    {
        $response->getBody()->write(json_encode(['success' => false, 'message' => $message]));
        return $response->withHeader('Content-Type', 'application/json')->withHeader('Cache-Control', 'no-store')->withStatus($status);
    }
}
