<?php

namespace App\Controllers;

use App\Core\Auth;
use App\Core\DB;
use Dompdf\Dompdf;
use Dompdf\Options;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;

class InvoicePdfController
{
    public function download(Request $request, Response $response, array $args): Response
    {
        $invoiceId  = (int)($args['id'] ?? 0);
        $businessId = Auth::businessId();

        if (!$invoiceId || !$businessId) {
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'Unauthorized']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        // ── Fetch invoice (must belong to this business) ─────────────────────
        $inv = DB::selectOne(
            'SELECT i.*,
                    c.name AS client_name, c.company AS client_company,
                    c.email AS client_email, c.mobile AS client_mobile,
                    c.gstin AS client_gstin, c.pan AS client_pan,
                    c.address_line1 AS client_address1, c.address_line2 AS client_address2,
                    c.city AS client_city, c.pincode AS client_pincode,
                    s.name AS place_of_supply_name, s.code AS place_of_supply_code
             FROM invoices i
             LEFT JOIN clients c ON c.id = i.client_id
             LEFT JOIN indian_states s ON s.id = i.place_of_supply
             WHERE i.id = ? AND i.business_id = ?
             LIMIT 1',
            [$invoiceId, $businessId]
        );

        if (!$inv) {
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'Invoice not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        // ── Fetch items ───────────────────────────────────────────────────────
        $items = DB::select(
            'SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY sort_order ASC',
            [$invoiceId]
        ) ?: [];

        // ── Fetch business ────────────────────────────────────────────────────
        $biz = DB::selectOne(
            'SELECT b.*, s.name AS state_name
             FROM businesses b
             LEFT JOIN indian_states s ON s.id = b.state_id
             WHERE b.id = ?',
            [$businessId]
        );

        $inv   = (array)$inv;
        $biz   = (array)($biz ?? []);
        $items = array_map(fn($r) => (array)$r, $items);

        // ── Generate HTML ─────────────────────────────────────────────────────
        $html = $this->renderHtml($inv, $items, $biz);

        // ── Convert to PDF ────────────────────────────────────────────────────
        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isFontSubsettingEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();

        $pdfContent = $dompdf->output();
        $filename   = preg_replace('/[^a-zA-Z0-9\-_]/', '-', $inv['number'] ?? 'invoice') . '.pdf';

        $response->getBody()->write($pdfContent);
        return $response
            ->withHeader('Content-Type', 'application/pdf')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->withHeader('Content-Length', (string)strlen($pdfContent));
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function inr(mixed $amount): string
    {
        $n = (float)($amount ?? 0);
        return '&#8377;' . number_format($n, 2, '.', ',');
    }

    private function fmtDate(?string $date): string
    {
        if (!$date) return '&mdash;';
        try {
            return (new \DateTime($date))->format('d M Y');
        } catch (\Throwable) {
            return htmlspecialchars($date, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        }
    }

    private function amountInWords(mixed $amount): string
    {
        $ones = ['','One','Two','Three','Four','Five','Six','Seven','Eight','Nine',
                 'Ten','Eleven','Twelve','Thirteen','Fourteen','Fifteen','Sixteen',
                 'Seventeen','Eighteen','Nineteen'];
        $tens = ['','','Twenty','Thirty','Forty','Fifty','Sixty','Seventy','Eighty','Ninety'];

        $toW = function(int $n) use (&$toW, $ones, $tens): string {
            if ($n === 0)        return '';
            if ($n < 20)         return $ones[$n] . ' ';
            if ($n < 100)        return $tens[(int)($n / 10)] . ' ' . ($ones[$n % 10] ? $ones[$n % 10] . ' ' : '');
            if ($n < 1000)       return $ones[(int)($n / 100)] . ' Hundred ' . $toW($n % 100);
            if ($n < 100000)     return $toW((int)($n / 1000))    . 'Thousand ' . $toW($n % 1000);
            if ($n < 10000000)   return $toW((int)($n / 100000))  . 'Lakh '     . $toW($n % 100000);
            return               $toW((int)($n / 10000000)) . 'Crore ' . $toW($n % 10000000);
        };

        $val = (float)($amount ?? 0);
        $n   = (int)round($val);
        $p   = (int)round(($val - floor($val)) * 100);
        $str = (trim($toW($n)) ?: 'Zero') . ' Rupees';
        if ($p > 0) $str .= ' and ' . trim($toW($p)) . ' Paise';
        return $str . ' Only';
    }

    private function logoBase64(string $logoUrl): ?string
    {
        $parsed = parse_url($logoUrl, PHP_URL_PATH);
        if (!$parsed) return null;
        $filePath = dirname(__DIR__, 2) . $parsed;
        if (!file_exists($filePath)) return null;
        $raw = @file_get_contents($filePath);
        if (!$raw) return null;
        $ext  = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $mime = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg',
                 'gif' => 'image/gif', 'webp' => 'image/webp'][$ext] ?? 'image/png';
        return 'data:' . $mime . ';base64,' . base64_encode($raw);
    }

    private function h(mixed $v): string
    {
        return htmlspecialchars((string)($v ?? ''), ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }

    private function when(bool $cond, string $html): string
    {
        return $cond ? $html : '';
    }

    private function renderHtml(array $inv, array $items, array $biz): string
    {
        $isGst    = ($inv['invoice_type'] ?? '') !== 'bill_of_supply';
        $titleMap = [
            'tax_invoice'    => 'Tax Invoice',
            'bill_of_supply' => 'Bill of Supply',
            'retail'         => 'Retail Invoice',
            'export'         => 'Export Invoice',
        ];
        $title = $titleMap[$inv['invoice_type'] ?? ''] ?? 'Tax Invoice';

        $logoSrc = !empty($biz['logo']) ? ($this->logoBase64($biz['logo']) ?? '') : '';

        // UPI QR — use uploaded bank QR image (already base64 data-URL) if available
        $qrSrc = $biz['upi_qr_image'] ?? '';

        // Business display info
        $bizName  = $biz['name']  ?? '';
        $bizGstin = $biz['gstin'] ?? '';
        $bizAddr  = implode(', ', array_filter([
            $biz['address_line1'] ?? '',
            $biz['address_line2'] ?? '',
        ]));
        $bizCity    = implode(', ', array_filter([$biz['city'] ?? '', $biz['state_name'] ?? '', $biz['pincode'] ?? '']));
        $bizContact = implode(' · ',  array_filter([$biz['mobile'] ?? '', $biz['email'] ?? '']));

        // Client address
        $clientAddr = $this->h($inv['client_address1'] ?? '');
        if (!empty($inv['client_address2'])) $clientAddr .= ', ' . $this->h($inv['client_address2']);
        $clientCity = implode(' – ', array_filter([$inv['client_city'] ?? '', $inv['client_pincode'] ?? '']));

        // Items rows
        $itemsHtml = '';
        foreach ($items as $idx => $it) {
            $taxCell = '';
            if ($isGst) {
                if ((float)($it['cgst_amt'] ?? 0) > 0) {
                    $taxCell = '<div>CGST ' . $this->h($it['cgst_rate']) . '%: ' . $this->inr($it['cgst_amt']) . '</div>'
                             . '<div>SGST ' . $this->h($it['sgst_rate']) . '%: ' . $this->inr($it['sgst_amt']) . '</div>';
                } elseif ((float)($it['igst_amt'] ?? 0) > 0) {
                    $taxCell = 'IGST ' . $this->h($it['igst_rate']) . '%: ' . $this->inr($it['igst_amt']);
                } else {
                    $taxCell = '<span style="color:#9ca3af">Nil</span>';
                }
            }
            $itemsHtml .= '<tr style="border-bottom:1px solid #f3f4f6">'
                . '<td style="padding:6px 8px;color:#9ca3af;font-size:11px">' . ($idx + 1) . '</td>'
                . '<td style="padding:6px 8px"><div style="font-weight:500;color:#1f2937">' . $this->h($it['description']) . '</div>'
                . ($it['unit'] ? '<div style="font-size:10px;color:#9ca3af">' . $this->h($it['unit']) . '</div>' : '')
                . '</td>'
                . '<td style="padding:6px 8px;text-align:center;font-family:monospace;font-size:11px;color:#6b7280">' . $this->h($it['hsn_sac'] ?: '—') . '</td>'
                . '<td style="padding:6px 8px;text-align:right;color:#374151">' . $this->h($it['quantity']) . '</td>'
                . '<td style="padding:6px 8px;text-align:right;color:#374151">' . $this->inr($it['unit_price']) . '</td>'
                . '<td style="padding:6px 8px;text-align:right;color:#374151">' . $this->inr($it['taxable_amt']) . '</td>'
                . ($isGst ? '<td style="padding:6px 8px;text-align:right;font-size:11px;color:#4b5563">' . $taxCell . '</td>' : '')
                . '<td style="padding:6px 8px;text-align:right;font-weight:600;color:#111827">' . $this->inr($it['total']) . '</td>'
                . '</tr>';
        }

        // Totals
        $totalsHtml  = '<div style="display:flex;justify-content:space-between;margin-bottom:4px;font-size:12px;color:#4b5563"><span>Subtotal</span><span>' . $this->inr($inv['subtotal'] ?? 0) . '</span></div>';
        if ((float)($inv['cgst_total'] ?? 0) > 0) $totalsHtml .= '<div style="display:flex;justify-content:space-between;margin-bottom:4px;font-size:12px;color:#4b5563"><span>CGST</span><span>'  . $this->inr($inv['cgst_total']) . '</span></div>';
        if ((float)($inv['sgst_total'] ?? 0) > 0) $totalsHtml .= '<div style="display:flex;justify-content:space-between;margin-bottom:4px;font-size:12px;color:#4b5563"><span>SGST</span><span>'  . $this->inr($inv['sgst_total']) . '</span></div>';
        if ((float)($inv['igst_total'] ?? 0) > 0) $totalsHtml .= '<div style="display:flex;justify-content:space-between;margin-bottom:4px;font-size:12px;color:#4b5563"><span>IGST</span><span>'  . $this->inr($inv['igst_total']) . '</span></div>';
        if ((float)($inv['discount']   ?? 0) > 0) $totalsHtml .= '<div style="display:flex;justify-content:space-between;margin-bottom:4px;font-size:12px;color:#ef4444"><span>Discount</span><span>-' . $this->inr($inv['discount'])   . '</span></div>';
        if ((float)($inv['round_off']  ?? 0) != 0) $totalsHtml .= '<div style="display:flex;justify-content:space-between;margin-bottom:4px;font-size:12px;color:#9ca3af"><span>Round Off</span><span>' . $this->inr($inv['round_off']) . '</span></div>';
        $totalsHtml .= '<div style="display:flex;justify-content:space-between;font-weight:700;font-size:14px;color:#111827;border-top:1px solid #d1d5db;padding-top:6px;margin-top:4px"><span>Total</span><span>' . $this->inr($inv['total'] ?? 0) . '</span></div>';
        if ((float)($inv['amount_paid'] ?? 0) > 0) $totalsHtml .= '<div style="display:flex;justify-content:space-between;margin-top:4px;font-size:12px;color:#15803d"><span>Amount Paid</span><span>'     . $this->inr($inv['amount_paid']) . '</span></div>';
        if ((float)($inv['amount_due']  ?? 0) > 0) $totalsHtml .= '<div style="display:flex;justify-content:space-between;font-weight:700;font-size:13px;color:#dc2626;border-top:1px solid #d1d5db;padding-top:6px;margin-top:4px"><span>Balance Due</span><span>' . $this->inr($inv['amount_due']) . '</span></div>';

        // Payment details (bank + UPI)
        $bankHtml = '';
        if (!empty($biz['bank_name']) || !empty($biz['upi_id'])) {
            $bankRows = '';
            if (!empty($biz['bank_name']))       $bankRows .= '<div style="display:flex;gap:8px;margin-bottom:3px"><span style="color:#9ca3af;width:64px;flex-shrink:0">Bank</span><span>'                        . $this->h($biz['bank_name'])       . '</span></div>';
            if (!empty($biz['bank_account_no'])) $bankRows .= '<div style="display:flex;gap:8px;margin-bottom:3px"><span style="color:#9ca3af;width:64px;flex-shrink:0">A/C No</span><span style="font-family:monospace">' . $this->h($biz['bank_account_no']) . '</span></div>';
            if (!empty($biz['bank_ifsc']))        $bankRows .= '<div style="display:flex;gap:8px;margin-bottom:3px"><span style="color:#9ca3af;width:64px;flex-shrink:0">IFSC</span><span style="font-family:monospace">'  . $this->h($biz['bank_ifsc'])       . '</span></div>';
            if (!empty($biz['upi_id']))           $bankRows .= '<div style="display:flex;gap:8px;margin-bottom:3px"><span style="color:#9ca3af;width:64px;flex-shrink:0">UPI ID</span><span style="font-family:monospace">' . $this->h($biz['upi_id'])          . '</span></div>';

            $qrImg = '';
            if ($qrSrc && (float)($inv['amount_due'] ?? 0) > 0) {
                $qrImg = '<div style="text-align:center;flex-shrink:0"><img src="' . $qrSrc . '" style="width:80px;height:80px;border:1px solid #e5e7eb;border-radius:4px" alt="UPI QR" />'
                       . '<div style="font-size:9px;color:#9ca3af;margin-top:2px">Scan to Pay</div></div>';
            }

            $bankHtml = '<div style="margin-bottom:12px">'
                      . '<div style="font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:0.1em;margin-bottom:6px">Payment Details</div>'
                      . '<div style="display:flex;gap:16px;align-items:flex-start;font-size:12px">'
                      . '<div style="flex:1">' . $bankRows . '</div>' . $qrImg
                      . '</div></div>';
        }

        // Notes + Terms
        $notesHtml = '';
        $notes = $inv['notes'] ?? $biz['invoice_notes'] ?? '';
        $terms = $inv['terms'] ?? $biz['invoice_terms'] ?? '';
        if ($notes) $notesHtml .= '<div style="margin-bottom:8px"><div style="font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:0.1em;margin-bottom:3px">Notes</div><div style="font-size:12px;color:#4b5563">' . $this->h($notes) . '</div></div>';
        if ($terms) $notesHtml .= '<div><div style="font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:0.1em;margin-bottom:3px">Terms</div><div style="font-size:12px;color:#4b5563">' . $this->h($terms) . '</div></div>';

        $logoHtml = $logoSrc
            ? '<img src="' . $logoSrc . '" style="width:48px;height:48px;object-fit:contain;border-radius:6px;border:1px solid #f3f4f6;flex-shrink:0" alt="logo" />'
            : '';

        $taxTh = $isGst ? '<th style="padding:8px;text-align:right;font-size:11px;font-weight:600">Tax</th>' : '';

        return '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>' . $this->h($title) . ' &mdash; ' . $this->h($inv['number'] ?? '') . '</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: DejaVu Sans, sans-serif; background: white; color: #111; font-size: 13px; }
  .page { max-width: 800px; margin: 0 auto; padding: 28px; }
  table { width: 100%; border-collapse: collapse; }
  th, td { vertical-align: top; }
</style>
</head>
<body>
<div class="page">

  <div style="display:flex;align-items:center;justify-content:space-between;margin-bottom:16px;padding-bottom:12px;border-bottom:2px solid #1f2937">
    <div style="font-size:20px;font-weight:900;color:#1e40af;text-transform:uppercase;letter-spacing:0.1em">' . $this->h($title) . '</div>
    <div style="font-size:14px;font-weight:700;color:#374151">' . $this->h($inv['number'] ?? '') . '</div>
  </div>

  <div style="display:flex;align-items:flex-start;gap:12px;margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid #e5e7eb">
    ' . $logoHtml . '
    <div>
      <div style="font-size:15px;font-weight:700;color:#111827">' . $this->h($bizName) . '</div>
      ' . $this->when($bizAddr    !== '', '<div style="font-size:11px;color:#6b7280">' . $this->h($bizAddr) . '</div>') . '
      ' . $this->when($bizCity    !== '', '<div style="font-size:11px;color:#6b7280">' . $this->h($bizCity) . '</div>') . '
      ' . $this->when($bizContact !== '', '<div style="font-size:11px;color:#6b7280">' . $this->h($bizContact) . '</div>') . '
      ' . $this->when($bizGstin   !== '', '<div style="font-size:11px;color:#6b7280;font-family:monospace">GSTIN: ' . $this->h($bizGstin) . '</div>') . '
    </div>
  </div>

  <table style="margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid #e5e7eb">
    <tr>
      <td style="width:50%;padding-right:16px">
        <div style="font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:0.1em;margin-bottom:5px">Bill To</div>
        <div style="font-weight:700;color:#111827;font-size:13px">' . $this->h($inv['client_name'] ?? '') . '</div>
        ' . $this->when(!empty($inv['client_company']),  '<div style="font-size:12px;color:#4b5563">' . $this->h($inv['client_company'] ?? '') . '</div>') . '
        ' . $this->when(!empty($inv['client_address1']), '<div style="font-size:12px;color:#4b5563">' . $clientAddr . '</div>') . '
        ' . $this->when($clientCity !== '',              '<div style="font-size:12px;color:#4b5563">' . $this->h($clientCity) . '</div>') . '
        ' . $this->when(!empty($inv['client_gstin']),    '<div style="font-size:11px;color:#6b7280;font-family:monospace">GSTIN: ' . $this->h($inv['client_gstin'] ?? '') . '</div>') . '
        ' . $this->when(!empty($inv['client_mobile']),   '<div style="font-size:11px;color:#6b7280">' . $this->h($inv['client_mobile'] ?? '') . '</div>') . '
      </td>
      <td style="width:50%">
        <div style="font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:0.1em;margin-bottom:5px">Invoice Details</div>
        <table style="font-size:12px;width:100%">
          <tr><td style="color:#9ca3af;padding-bottom:4px;padding-right:12px">Invoice No</td><td style="font-weight:600;color:#1f2937">' . $this->h($inv['number'] ?? '') . '</td></tr>
          <tr><td style="color:#9ca3af;padding-bottom:4px;padding-right:12px">Invoice Date</td><td style="color:#374151">' . $this->fmtDate($inv['issue_date'] ?? null) . '</td></tr>
          <tr><td style="color:#9ca3af;padding-bottom:4px;padding-right:12px">Due Date</td><td style="color:#374151">' . $this->fmtDate($inv['due_date'] ?? null) . '</td></tr>
          <tr><td style="color:#9ca3af;padding-right:12px">Place of Supply</td><td style="color:#374151">' . $this->h($inv['place_of_supply_name'] ?? $inv['supply_type'] ?? '') . '</td></tr>
        </table>
      </td>
    </tr>
  </table>

  <table style="margin-bottom:16px;font-size:12px">
    <thead>
      <tr style="background-color:#1f2937;color:white">
        <th style="padding:8px;text-align:left;font-size:11px;font-weight:600;width:28px">#</th>
        <th style="padding:8px;text-align:left;font-size:11px;font-weight:600">Description</th>
        <th style="padding:8px;text-align:center;font-size:11px;font-weight:600">HSN/SAC</th>
        <th style="padding:8px;text-align:right;font-size:11px;font-weight:600">Qty</th>
        <th style="padding:8px;text-align:right;font-size:11px;font-weight:600">Rate</th>
        <th style="padding:8px;text-align:right;font-size:11px;font-weight:600">Taxable</th>
        ' . $taxTh . '
        <th style="padding:8px;text-align:right;font-size:11px;font-weight:600">Amount</th>
      </tr>
    </thead>
    <tbody>' . $itemsHtml . '</tbody>
  </table>

  <table style="margin-bottom:20px;padding-bottom:16px;border-bottom:1px solid #e5e7eb">
    <tr>
      <td style="padding-right:24px">
        <div style="font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:0.1em;margin-bottom:5px">Amount in Words</div>
        <div style="font-size:12px;font-weight:500;color:#374151;font-style:italic">' . $this->h($this->amountInWords($inv['total'] ?? 0)) . '</div>
      </td>
      <td style="width:210px">
        ' . $totalsHtml . '
      </td>
    </tr>
  </table>

  <table style="margin-bottom:24px">
    <tr>
      <td style="width:50%;padding-right:24px">' . $bankHtml . '</td>
      <td style="width:50%">' . $notesHtml . '</td>
    </tr>
  </table>

  <table style="margin-top:32px">
    <tr>
      <td style="width:50%;text-align:center;padding-right:32px">
        <div style="border-top:1px solid #9ca3af;padding-top:8px;margin-top:48px;font-size:11px;font-weight:600;color:#6b7280">Customer Signature</div>
      </td>
      <td style="width:50%;text-align:center;padding-left:32px">
        <div style="border-top:1px solid #9ca3af;padding-top:8px;margin-top:48px;font-size:11px;font-weight:600;color:#6b7280">Authorised Signatory</div>
      </td>
    </tr>
  </table>

</div>
</body>
</html>';
    }
}
