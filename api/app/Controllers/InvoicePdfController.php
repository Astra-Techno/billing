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
    // Brand colors — match Vue classic template
    private const NAVY   = '#1f2937'; // gray-800 (table header)
    private const ACCENT = '#1e40af'; // blue-800 (title)
    private const LIGHT  = '#f9fafb'; // gray-50
    private const BORDER = '#e5e7eb'; // gray-200
    private const MUTED  = '#6b7280'; // gray-500
    private const DARK   = '#1f2937'; // gray-800

    /** Used by the GET /invoice/{id}/pdf route. Supports ?mode=dc|proforma */
    public function download(Request $request, Response $response, array $args): Response
    {
        $invoiceId  = (int)($args['id'] ?? 0);
        $businessId = Auth::businessId();
        $mode       = $request->getQueryParams()['mode'] ?? 'normal';

        if (!$invoiceId || !$businessId) {
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'Unauthorized']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        [$pdfContent, $filename] = $this->generatePdf($invoiceId, $businessId, $mode);

        if ($pdfContent === null) {
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'Invoice not found']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(404);
        }

        $response->getBody()->write($pdfContent);
        return $response
            ->withHeader('Content-Type', 'application/pdf')
            ->withHeader('Content-Disposition', 'attachment; filename="' . $filename . '"')
            ->withHeader('Content-Length', (string)strlen($pdfContent));
    }

    /**
     * Generate PDF bytes for an invoice. Returns [pdfBytes, filename] or [null, ''] on failure.
     * Public so the Invoice Task can call it directly.
     */
    public function generatePdf(int $invoiceId, int $businessId, string $mode = 'normal'): array
    {
        $inv = DB::selectOne(
            'SELECT i.*,
                    c.name AS client_name, c.company AS client_company,
                    c.email AS client_email, c.mobile AS client_mobile,
                    c.gstin AS client_gstin, c.pan AS client_pan,
                    c.address_line1 AS client_address1, c.address_line2 AS client_address2,
                    c.city AS client_city, c.pincode AS client_pincode,
                    s.name AS place_of_supply_name
             FROM invoices i
             LEFT JOIN clients c ON c.id = i.client_id
             LEFT JOIN indian_states s ON s.id = i.place_of_supply
             WHERE i.id = ? AND i.business_id = ? AND i.deleted_at IS NULL
             LIMIT 1',
            [$invoiceId, $businessId]
        );

        if (!$inv) return [null, ''];

        $items = DB::select(
            'SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY sort_order ASC',
            [$invoiceId]
        ) ?: [];

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

        $html = $this->renderHtml($inv, $items, $biz, $mode);

        $options = new Options();
        $options->set('isHtml5ParserEnabled', true);
        $options->set('isRemoteEnabled', false);
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isFontSubsettingEnabled', true);
        if (($_ENV['DESKTOP_MODE'] ?? '') === 'true') {
            $fontCache = $_ENV['STORAGE_PATH'] . '/cache/fonts';
            if (!is_dir($fontCache)) mkdir($fontCache, 0700, true);
            $options->set('fontCache', $fontCache);
            $options->set('tempDir', $fontCache);
        }

        $dompdf = new Dompdf($options);
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->render();

        $pdfContent = $dompdf->output();
        $prefix     = $mode === 'dc' ? 'DC-' : ($mode === 'proforma' ? 'PROFORMA-' : '');
        $filename   = $prefix . preg_replace('/[^a-zA-Z0-9\-_]/', '-', $inv['number'] ?? 'invoice') . '.pdf';

        return [$pdfContent, $filename];
    }

    // ── Formatting helpers ────────────────────────────────────────────────────

    private function inr(mixed $amount): string
    {
        return '&#8377;' . number_format((float)($amount ?? 0), 2, '.', ',');
    }

    private function fmtDate(?string $date): string
    {
        if (!$date) return '&mdash;';
        try { return (new \DateTime($date))->format('d M Y'); }
        catch (\Throwable) { return $this->h($date); }
    }

    /** Escape and convert extended Unicode to HTML entities so Dompdf renders correctly */
    private function h(mixed $v): string
    {
        $s = (string)($v ?? '');
        // Ensure valid UTF-8
        $s = mb_convert_encoding($s, 'UTF-8', 'UTF-8');
        // Convert special HTML chars
        $s = htmlspecialchars($s, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        // Convert extended Unicode to numeric entities (Dompdf handles these reliably)
        $s = preg_replace_callback('/[^\x00-\x7F]/u', function ($m) {
            $cp = mb_ord($m[0], 'UTF-8');
            return '&#' . $cp . ';';
        }, $s);
        return $s ?? '';
    }

    private function when(bool $cond, string $html): string
    {
        return $cond ? $html : '';
    }

    private function amountInWords(mixed $amount): string
    {
        $ones = ['','One','Two','Three','Four','Five','Six','Seven','Eight','Nine',
                 'Ten','Eleven','Twelve','Thirteen','Fourteen','Fifteen','Sixteen',
                 'Seventeen','Eighteen','Nineteen'];
        $tens = ['','','Twenty','Thirty','Forty','Fifty','Sixty','Seventy','Eighty','Ninety'];

        $toW = function(int $n) use (&$toW, $ones, $tens): string {
            if ($n === 0)      return '';
            if ($n < 20)       return $ones[$n] . ' ';
            if ($n < 100)      return $tens[(int)($n/10)] . ' ' . ($ones[$n%10] ? $ones[$n%10] . ' ' : '');
            if ($n < 1000)     return $ones[(int)($n/100)] . ' Hundred ' . $toW($n%100);
            if ($n < 100000)   return $toW((int)($n/1000))    . 'Thousand ' . $toW($n%1000);
            if ($n < 10000000) return $toW((int)($n/100000))  . 'Lakh '     . $toW($n%100000);
            return               $toW((int)($n/10000000)) . 'Crore ' . $toW($n%10000000);
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
        $filePath = (($_ENV['DESKTOP_MODE'] ?? '') === 'true')
            ? $_ENV['STORAGE_PATH'] . '/logos/' . basename($parsed)
            : dirname(__DIR__, 2) . $parsed;
        if (!file_exists($filePath)) return null;
        $raw = @file_get_contents($filePath);
        if (!$raw) return null;
        $ext  = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $mime = ['png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg',
                 'gif'=>'image/gif','webp'=>'image/webp'][$ext] ?? 'image/png';
        return 'data:' . $mime . ';base64,' . base64_encode($raw);
    }

    // ── HTML template ─────────────────────────────────────────────────────────

    private function renderHtml(array $inv, array $items, array $biz, string $mode = 'normal'): string
    {
        $isDC       = $mode === 'dc';
        $isProforma = $mode === 'proforma';
        $isGst      = !$isDC && ($inv['invoice_type'] ?? '') !== 'bill_of_supply';
        $titles = [
            'tax_invoice'    => 'Tax Invoice',
            'bill_of_supply' => 'Bill of Supply',
            'retail'         => 'Retail Invoice',
            'export'         => 'Export Invoice',
            'proforma'       => 'Proforma Invoice',
        ];
        if ($isDC) {
            $title = 'Delivery Challan';
        } elseif ($isProforma) {
            $title = 'Proforma Invoice';
        } else {
            $title = $titles[$inv['invoice_type'] ?? ''] ?? 'Tax Invoice';
        }

        $logoSrc = !empty($biz['logo']) ? ($this->logoBase64($biz['logo']) ?? '') : '';

        // Business info
        $bizName    = $this->h($biz['name'] ?? '');
        $bizGstin   = $this->h($biz['gstin'] ?? '');
        $bizAddr    = implode(', ', array_filter([$biz['address_line1'] ?? '', $biz['address_line2'] ?? '']));
        $bizCity    = implode(', ', array_filter([$biz['city'] ?? '', $biz['state_name'] ?? '', $biz['pincode'] ?? '']));
        $bizContact = implode(' &middot; ', array_filter([
            $biz['mobile'] ? $this->h($biz['mobile']) : '',
            $biz['email']  ? $this->h($biz['email'])  : '',
        ]));

        // Client info
        $clientAddr = $this->h($inv['client_address1'] ?? '');
        if (!empty($inv['client_address2'])) $clientAddr .= ', ' . $this->h($inv['client_address2']);
        $clientCity = implode(' &ndash; ', array_filter([$inv['client_city'] ?? '', $inv['client_pincode'] ?? '']));

        // ── Items table rows ────────────────────────────────────────────────
        $itemsHtml = '';
        $totalQty  = 0;
        foreach ($items as $idx => $it) {
            $totalQty += (float)($it['quantity'] ?? 0);
            $taxCell = '';
            if ($isGst) {
                if ((float)($it['cgst_amt'] ?? 0) > 0) {
                    $r = $it['gst_rate'] / 2;
                    $taxCell = '<div style="font-size:11px;color:#4b5563">CGST ' . $r . '%: ' . $this->inr($it['cgst_amt']) . '</div>'
                             . '<div style="font-size:11px;color:#4b5563">SGST ' . $r . '%: ' . $this->inr($it['sgst_amt']) . '</div>';
                } elseif ((float)($it['igst_amt'] ?? 0) > 0) {
                    $taxCell = '<div style="font-size:11px;color:#4b5563">IGST ' . $this->h($it['gst_rate']) . '%: ' . $this->inr($it['igst_amt']) . '</div>';
                } else {
                    $taxCell = '<span style="color:#9ca3af">Nil</span>';
                }
            }

            $itemsHtml .= '<tr style="border-bottom:1px solid #f3f4f6">'
                . '<td style="padding:8px;color:#9ca3af;font-size:11px">' . ($idx + 1) . '</td>'
                . '<td style="padding:8px;font-weight:600;color:#1f2937">' . $this->h($it['description']) . '</td>'
                . '<td style="padding:8px;text-align:center;font-family:monospace;font-size:11px;color:#6b7280">' . ($it['hsn_sac'] ? $this->h($it['hsn_sac']) : '&mdash;') . '</td>'
                . '<td style="padding:8px;text-align:right;color:#374151">' . $this->h($it['quantity']) . '</td>'
                . '<td style="padding:8px;text-align:center;color:#374151;font-size:11px">' . $this->h($it['unit'] ?? 'Nos') . '</td>'
                . ($isDC ? '' : '<td style="padding:8px;text-align:right;color:#374151">' . $this->inr($it['unit_price']) . '</td>')
                . ($isDC ? '' : '<td style="padding:8px;text-align:right;color:#374151">' . $this->inr($it['taxable_amt']) . '</td>')
                . ($isGst ? '<td style="padding:8px;text-align:right">' . $taxCell . '</td>' : '')
                . ($isDC ? '' : '<td style="padding:8px;text-align:right;font-weight:600;color:#111827">' . $this->inr($it['total']) . '</td>')
                . '</tr>';
        }

        // ── Totals ──────────────────────────────────────────────────────────
        $grossSub = (float)($inv['subtotal'] ?? 0) + (float)($inv['discount'] ?? 0);
        $totalsHtml = '<div style="font-size:12px;color:#4b5563">'
            . '<div style="display:flex;justify-content:space-between;padding:2px 0"><span>Subtotal</span><span>' . $this->inr($grossSub) . '</span></div>';
        if ((float)($inv['discount'] ?? 0) > 0)
            $totalsHtml .= '<div style="display:flex;justify-content:space-between;padding:2px 0;color:#15803d"><span>Discount</span><span>&minus;' . $this->inr($inv['discount']) . '</span></div>';
        if ((float)($inv['cgst_total'] ?? 0) > 0)
            $totalsHtml .= '<div style="display:flex;justify-content:space-between;padding:2px 0"><span>CGST</span><span>' . $this->inr($inv['cgst_total']) . '</span></div>';
        if ((float)($inv['sgst_total'] ?? 0) > 0)
            $totalsHtml .= '<div style="display:flex;justify-content:space-between;padding:2px 0"><span>SGST</span><span>' . $this->inr($inv['sgst_total']) . '</span></div>';
        if ((float)($inv['igst_total'] ?? 0) > 0)
            $totalsHtml .= '<div style="display:flex;justify-content:space-between;padding:2px 0"><span>IGST</span><span>' . $this->inr($inv['igst_total']) . '</span></div>';
        $totalsHtml .= '<div style="display:flex;justify-content:space-between;padding:4px 0;font-weight:700;font-size:14px;color:#111827;border-top:1px solid #d1d5db;margin-top:4px"><span>Total</span><span>' . $this->inr($inv['total'] ?? 0) . '</span></div>';
        if ((float)($inv['amount_paid'] ?? 0) > 0)
            $totalsHtml .= '<div style="display:flex;justify-content:space-between;padding:2px 0;color:#15803d"><span>Paid</span><span>' . $this->inr($inv['amount_paid']) . '</span></div>';
        if ((float)($inv['amount_due'] ?? 0) > 0)
            $totalsHtml .= '<div style="display:flex;justify-content:space-between;padding:4px 0;font-weight:700;color:#dc2626;border-top:1px solid #d1d5db;margin-top:4px"><span>Balance Due</span><span>' . $this->inr($inv['amount_due']) . '</span></div>';
        $totalsHtml .= '</div>';

        // ── Bank details ────────────────────────────────────────────────────
        $bankHtml = '';
        $hasBank  = !empty($biz['bank_name']) || !empty($biz['upi_id']);
        if ($hasBank) {
            $bankHtml = '<div style="font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:0.1em;margin-bottom:4px">Payment Details</div><div style="font-size:11px;line-height:1.8">';
            if (!empty($biz['bank_name']))       $bankHtml .= '<div><span style="color:#9ca3af;display:inline-block;width:42px">Bank</span> ' . $this->h($biz['bank_name']) . '</div>';
            if (!empty($biz['bank_account_no'])) $bankHtml .= '<div style="font-family:monospace"><span style="color:#9ca3af;display:inline-block;width:42px;font-family:DejaVu Sans,sans-serif">A/C</span> ' . $this->h($biz['bank_account_no']) . '</div>';
            if (!empty($biz['bank_ifsc']))        $bankHtml .= '<div style="font-family:monospace"><span style="color:#9ca3af;display:inline-block;width:42px;font-family:DejaVu Sans,sans-serif">IFSC</span> ' . $this->h($biz['bank_ifsc']) . '</div>';
            if (!empty($biz['upi_id']))           $bankHtml .= '<div style="font-family:monospace"><span style="color:#9ca3af;display:inline-block;width:42px;font-family:DejaVu Sans,sans-serif">UPI</span> ' . $this->h($biz['upi_id']) . '</div>';
            $bankHtml .= '</div>';
        }

        // ── Notes + Terms ───────────────────────────────────────────────────
        $notesHtml = '';
        $notes = $inv['notes'] ?? $biz['invoice_notes'] ?? '';
        $terms = $inv['terms'] ?? $biz['invoice_terms'] ?? '';
        if ($notes) $notesHtml .= '<div style="margin-bottom:8px"><div style="font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:0.1em;margin-bottom:2px">Notes</div><div style="font-size:11px;color:#4b5563">' . $this->h($notes) . '</div></div>';
        if ($terms) $notesHtml .= '<div><div style="font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:0.1em;margin-bottom:2px">Terms</div><div style="font-size:11px;color:#4b5563">' . $this->h($terms) . '</div></div>';

        // ── Logo HTML ───────────────────────────────────────────────────────
        $logoHtml = $logoSrc
            ? '<img src="' . $logoSrc . '" style="width:48px;height:48px;object-fit:contain;border-radius:8px;border:1px solid #f3f4f6" />'
            : '';

        // ── Table headers ───────────────────────────────────────────────────
        $thStyle = 'padding:8px;font-size:11px;font-weight:600;color:white';

        // DC/Proforma disclaimers
        $dcSummary = $isDC
            ? '<div style="padding:12px 0;text-align:right;font-size:12px;color:#111827">Total Items: <strong>' . count($items) . '</strong> &nbsp; Total Qty: <strong>' . $totalQty . '</strong></div>'
            : '';
        $proformaDisclaimer = $isProforma
            ? '<div style="margin:16px 0;padding:8px 12px;border:2px dashed #fbbf24;background:#fffbeb;text-align:center;font-size:11px;font-weight:700;color:#b45309;text-transform:uppercase;letter-spacing:0.08em">This is not a tax invoice &mdash; for reference only</div>'
            : '';

        return '<!DOCTYPE html>
<html lang="en">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<title>' . $this->h($title) . ' &mdash; ' . $this->h($inv['number'] ?? '') . '</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: DejaVu Sans, sans-serif; background: white; color: #111827; font-size: 12px; }
  .page { max-width: 780px; margin: 0 auto; padding: 20px; background: white; }
  table { width: 100%; border-collapse: collapse; }
  td, th { vertical-align: top; }
</style>
</head>
<body>
<div class="page">

  <!-- Title row -->
  <div style="display:flex;justify-content:space-between;align-items:center;padding-bottom:12px;margin-bottom:16px;border-bottom:2px solid #1f2937">
    <div style="font-size:20px;font-weight:900;color:#1e40af;text-transform:uppercase;letter-spacing:0.1em">' . strtoupper($title) . '</div>
    <div style="font-size:14px;font-weight:700;color:#374151">' . $this->h($inv['number'] ?? '') . '</div>
  </div>

  <!-- Business info -->
  <div style="display:flex;align-items:flex-start;gap:12px;margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid #e5e7eb">
    ' . $logoHtml . '
    <div>
      <div style="font-size:14px;font-weight:700;color:#111827">' . $bizName . '</div>
      ' . $this->when($bizAddr !== '', '<div style="font-size:11px;color:#6b7280">' . $this->h($bizAddr) . '</div>') . '
      ' . $this->when($bizCity !== '', '<div style="font-size:11px;color:#6b7280">' . $this->h($bizCity) . '</div>') . '
      ' . $this->when($bizContact !== '', '<div style="font-size:11px;color:#6b7280">' . $bizContact . '</div>') . '
      ' . $this->when($bizGstin !== '', '<div style="font-size:11px;color:#6b7280;font-family:monospace">GSTIN: ' . $bizGstin . '</div>') . '
    </div>
  </div>

  <!-- Bill To + Invoice Details -->
  <table style="margin-bottom:16px;padding-bottom:12px;border-bottom:1px solid #e5e7eb">
    <tr>
      <td style="width:50%;padding-right:16px">
        <div style="font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:0.1em;margin-bottom:4px">Bill To</div>
        <div style="font-size:13px;font-weight:700;color:#111827">' . $this->h($inv['client_name'] ?? 'Walk-in Customer') . '</div>
        ' . $this->when(!empty($inv['client_company']), '<div style="font-size:11px;color:#4b5563">' . $this->h($inv['client_company'] ?? '') . '</div>') . '
        ' . $this->when(!empty($inv['client_gstin']),   '<div style="font-size:11px;color:#6b7280;font-family:monospace">GSTIN: ' . $this->h($inv['client_gstin'] ?? '') . '</div>') . '
        ' . $this->when(!empty($inv['client_mobile']),  '<div style="font-size:11px;color:#6b7280">Mob: ' . $this->h($inv['client_mobile'] ?? '') . '</div>') . '
      </td>
      <td style="width:50%">
        <div style="font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:0.1em;margin-bottom:4px">' . ($isDC ? 'Challan Details' : 'Invoice Details') . '</div>
        <table style="font-size:12px">
          <tr><td style="color:#9ca3af;padding-bottom:4px;padding-right:12px">' . ($isDC ? 'Ref Invoice' : 'Invoice No') . '</td><td style="font-weight:600;color:#1f2937">' . $this->h($inv['number'] ?? '') . '</td></tr>
          <tr><td style="color:#9ca3af;padding-bottom:4px;padding-right:12px">' . ($isDC ? 'Challan Date' : 'Invoice Date') . '</td><td style="color:#374151">' . $this->fmtDate($inv['issue_date'] ?? null) . '</td></tr>
          ' . ($isDC ? '' : '<tr><td style="color:#9ca3af;padding-bottom:4px;padding-right:12px">Due Date</td><td style="color:#374151">' . $this->fmtDate($inv['due_date'] ?? null) . '</td></tr>') . '
          <tr><td style="color:#9ca3af;padding-right:12px">Place of Supply</td><td style="color:#374151">' . $this->h($inv['place_of_supply_name'] ?? $inv['supply_type'] ?? '') . '</td></tr>
        </table>
      </td>
    </tr>
  </table>

  <!-- Items -->
  <table style="margin-bottom:16px">
    <thead>
      <tr style="background:#1f2937">
        <th style="' . $thStyle . ';text-align:left;width:24px">#</th>
        <th style="' . $thStyle . ';text-align:left">Description</th>
        <th style="' . $thStyle . ';text-align:center">HSN/SAC</th>
        <th style="' . $thStyle . ';text-align:right">Qty</th>
        <th style="' . $thStyle . ';text-align:center">Unit</th>
        ' . ($isDC ? '' : '<th style="' . $thStyle . ';text-align:right">Rate</th>') . '
        ' . ($isDC ? '' : '<th style="' . $thStyle . ';text-align:right">Taxable</th>') . '
        ' . ($isGst ? '<th style="' . $thStyle . ';text-align:right">Tax</th>' : '') . '
        ' . ($isDC ? '' : '<th style="' . $thStyle . ';text-align:right">Amount</th>') . '
      </tr>
    </thead>
    <tbody>' . $itemsHtml . '</tbody>
  </table>

  ' . $proformaDisclaimer . '
  ' . $dcSummary . '

  ' . ($isDC ? '' : '
  <!-- Totals + Amount in Words -->
  <div style="display:flex;gap:24px;margin-bottom:16px;padding-bottom:16px;border-bottom:1px solid #e5e7eb">
    <div style="flex:1">
      <div style="font-size:10px;font-weight:700;color:#9ca3af;text-transform:uppercase;letter-spacing:0.1em;margin-bottom:4px">Amount in Words</div>
      <div style="font-size:12px;font-weight:500;color:#374151;font-style:italic">' . $this->h($this->amountInWords($inv['total'] ?? 0)) . '</div>
    </div>
    <div style="width:210px">' . $totalsHtml . '</div>
  </div>') . '

  <!-- Bank + Notes -->
  <table style="margin-bottom:24px">
    <tr>
      ' . ($hasBank && !$isDC ? '<td style="width:50%;padding-right:24px;vertical-align:top">' . $bankHtml . '</td>' : '') . '
      <td style="vertical-align:top">' . $notesHtml . '</td>
    </tr>
  </table>

  <!-- Signatures -->
  <table style="margin-top:32px">
    <tr>
      <td style="width:50%;text-align:center;padding-right:32px">
        <div style="height:48px;border-bottom:1px solid #9ca3af;margin-bottom:8px"></div>
        <div style="font-size:11px;font-weight:600;color:#6b7280">Customer Signature</div>
      </td>
      <td style="width:50%;text-align:center;padding-left:32px">
        <div style="height:48px;border-bottom:1px solid #9ca3af;margin-bottom:8px"></div>
        <div style="font-size:11px;font-weight:600;color:#6b7280">Authorised Signatory</div>
      </td>
    </tr>
  </table>

</div>
</body>
</html>';
    }

    private function totalRow(string $label, string $value, string $color, string $size, bool $bold): string
    {
        $w = $bold ? '700' : '400';
        return '<tr style="border-bottom:1px solid ' . self::BORDER . '">'
            . '<td style="padding:8px 14px;font-size:' . $size . ';color:' . $color . ';font-weight:' . $w . '">' . $label . '</td>'
            . '<td style="padding:8px 14px;text-align:right;font-size:' . $size . ';color:' . $color . ';font-weight:' . $w . '">' . $value . '</td>'
            . '</tr>';
    }

    private function bankRow(string $label, string $value): string
    {
        return '<tr><td style="color:' . self::MUTED . ';padding-bottom:5px;padding-right:12px;white-space:nowrap;font-size:11px">' . $label . '</td>'
             . '<td style="color:' . self::DARK . ';padding-bottom:5px;font-size:11px">' . $value . '</td></tr>';
    }
}
