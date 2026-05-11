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
    // Brand colors
    private const NAVY   = '#0f1f3d';
    private const ACCENT = '#1d4ed8';
    private const LIGHT  = '#f0f4ff';
    private const BORDER = '#e2e8f0';
    private const MUTED  = '#64748b';
    private const DARK   = '#1e293b';

    /** Used by the GET /invoice/{id}/pdf route */
    public function download(Request $request, Response $response, array $args): Response
    {
        $invoiceId  = (int)($args['id'] ?? 0);
        $businessId = Auth::businessId();

        if (!$invoiceId || !$businessId) {
            $response->getBody()->write(json_encode(['success' => false, 'message' => 'Unauthorized']));
            return $response->withHeader('Content-Type', 'application/json')->withStatus(403);
        }

        [$pdfContent, $filename] = $this->generatePdf($invoiceId, $businessId);

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
    public function generatePdf(int $invoiceId, int $businessId): array
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
             WHERE i.id = ? AND i.business_id = ?
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

        $html = $this->renderHtml($inv, $items, $biz);

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
        $filePath = dirname(__DIR__, 2) . $parsed;
        if (!file_exists($filePath)) return null;
        $raw = @file_get_contents($filePath);
        if (!$raw) return null;
        $ext  = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $mime = ['png'=>'image/png','jpg'=>'image/jpeg','jpeg'=>'image/jpeg',
                 'gif'=>'image/gif','webp'=>'image/webp'][$ext] ?? 'image/png';
        return 'data:' . $mime . ';base64,' . base64_encode($raw);
    }

    // ── HTML template ─────────────────────────────────────────────────────────

    private function renderHtml(array $inv, array $items, array $biz): string
    {
        $isGst = ($inv['invoice_type'] ?? '') !== 'bill_of_supply';
        $titles = [
            'tax_invoice'    => 'Tax Invoice',
            'bill_of_supply' => 'Bill of Supply',
            'retail'         => 'Retail Invoice',
            'export'         => 'Export Invoice',
        ];
        $title = $titles[$inv['invoice_type'] ?? ''] ?? 'Tax Invoice';

        $logoSrc = !empty($biz['logo']) ? ($this->logoBase64($biz['logo']) ?? '') : '';
        $qrSrc   = $biz['upi_qr_image'] ?? '';

        // Business info
        $bizName    = $this->h($biz['name'] ?? '');
        $bizGstin   = $this->h($biz['gstin'] ?? '');
        $bizAddr    = implode(', ', array_filter([$biz['address_line1'] ?? '', $biz['address_line2'] ?? '']));
        $bizCity    = implode(', ', array_filter([$biz['city'] ?? '', $biz['state_name'] ?? '', $biz['pincode'] ?? '']));
        $bizContact = implode(' &nbsp;|&nbsp; ', array_filter([
            $biz['mobile'] ? $this->h($biz['mobile']) : '',
            $biz['email']  ? $this->h($biz['email'])  : '',
        ]));

        // Client info
        $clientAddr = $this->h($inv['client_address1'] ?? '');
        if (!empty($inv['client_address2'])) $clientAddr .= ', ' . $this->h($inv['client_address2']);
        $clientCity = implode(' &ndash; ', array_filter([$inv['client_city'] ?? '', $inv['client_pincode'] ?? '']));

        // ── Items table body ──────────────────────────────────────────────────
        $colSpan   = $isGst ? 8 : 7;
        $itemsHtml = '';
        foreach ($items as $idx => $it) {
            $bg = ($idx % 2 === 0) ? '#ffffff' : '#f8fafc';

            $taxCell = '';
            if ($isGst) {
                if ((float)($it['cgst_amt'] ?? 0) > 0) {
                    $taxCell = '<span style="display:block">CGST ' . $this->h($it['cgst_rate']) . '%: ' . $this->inr($it['cgst_amt']) . '</span>'
                             . '<span style="display:block">SGST ' . $this->h($it['sgst_rate']) . '%: ' . $this->inr($it['sgst_amt']) . '</span>';
                } elseif ((float)($it['igst_amt'] ?? 0) > 0) {
                    $taxCell = 'IGST ' . $this->h($it['igst_rate']) . '%: ' . $this->inr($it['igst_amt']);
                } else {
                    $taxCell = '<span style="color:' . self::MUTED . '">Nil</span>';
                }
            }

            $itemsHtml .= '<tr style="background:' . $bg . ';border-bottom:1px solid ' . self::BORDER . '">'
                . '<td style="padding:9px 10px;color:' . self::MUTED . ';font-size:11px;text-align:center">' . ($idx + 1) . '</td>'
                . '<td style="padding:9px 10px">'
                .   '<div style="font-weight:600;color:' . self::DARK . ';font-size:12px">' . $this->h($it['description']) . '</div>'
                .   ($it['unit'] ? '<div style="font-size:10px;color:' . self::MUTED . ';margin-top:1px">' . $this->h($it['unit']) . '</div>' : '')
                . '</td>'
                . '<td style="padding:9px 10px;text-align:center;font-family:monospace;font-size:11px;color:' . self::MUTED . '">' . ($it['hsn_sac'] ? $this->h($it['hsn_sac']) : '&mdash;') . '</td>'
                . '<td style="padding:9px 10px;text-align:right;color:' . self::DARK . ';font-size:12px">' . $this->h($it['quantity']) . '</td>'
                . '<td style="padding:9px 10px;text-align:right;color:' . self::DARK . ';font-size:12px">' . $this->inr($it['unit_price']) . '</td>'
                . '<td style="padding:9px 10px;text-align:right;color:' . self::DARK . ';font-size:12px">' . $this->inr($it['taxable_amt']) . '</td>'
                . ($isGst ? '<td style="padding:9px 10px;text-align:right;font-size:11px;color:#475569">' . $taxCell . '</td>' : '')
                . '<td style="padding:9px 10px;text-align:right;font-weight:700;font-size:12px;color:' . self::NAVY . '">' . $this->inr($it['total']) . '</td>'
                . '</tr>';
        }

        // ── Totals block ──────────────────────────────────────────────────────
        $totals = '';
        $totals .= $this->totalRow('Subtotal', $this->inr($inv['subtotal'] ?? 0), self::MUTED, '12px', false);
        if ((float)($inv['cgst_total'] ?? 0) > 0) $totals .= $this->totalRow('CGST', $this->inr($inv['cgst_total']), self::MUTED, '12px', false);
        if ((float)($inv['sgst_total'] ?? 0) > 0) $totals .= $this->totalRow('SGST', $this->inr($inv['sgst_total']), self::MUTED, '12px', false);
        if ((float)($inv['igst_total'] ?? 0) > 0) $totals .= $this->totalRow('IGST', $this->inr($inv['igst_total']), self::MUTED, '12px', false);
        if ((float)($inv['discount']   ?? 0) > 0) $totals .= $this->totalRow('Discount', '&minus;' . $this->inr($inv['discount']), '#dc2626', '12px', false);
        if ((float)($inv['round_off']  ?? 0) != 0) $totals .= $this->totalRow('Round Off', $this->inr($inv['round_off']), self::MUTED, '12px', false);

        // Grand total row — highlighted
        $totals .= '<tr style="background:' . self::NAVY . '">'
            . '<td style="padding:11px 14px;font-weight:700;font-size:13px;color:#fff;border-radius:0">Total</td>'
            . '<td style="padding:11px 14px;text-align:right;font-weight:700;font-size:13px;color:#fff">' . $this->inr($inv['total'] ?? 0) . '</td>'
            . '</tr>';

        if ((float)($inv['amount_paid'] ?? 0) > 0) $totals .= $this->totalRow('Amount Paid', $this->inr($inv['amount_paid']), '#16a34a', '12px', false);

        if ((float)($inv['amount_due'] ?? 0) > 0) {
            $totals .= '<tr style="background:#fef2f2">'
                . '<td style="padding:10px 14px;font-weight:700;font-size:13px;color:#dc2626">Balance Due</td>'
                . '<td style="padding:10px 14px;text-align:right;font-weight:700;font-size:13px;color:#dc2626">' . $this->inr($inv['amount_due']) . '</td>'
                . '</tr>';
        }

        // ── Payment details ───────────────────────────────────────────────────
        $bankHtml = '';
        $hasBank  = !empty($biz['bank_name']) || !empty($biz['upi_id']);
        if ($hasBank) {
            $rows = '';
            if (!empty($biz['bank_name']))       $rows .= $this->bankRow('Bank',    $this->h($biz['bank_name']));
            if (!empty($biz['bank_account_no'])) $rows .= $this->bankRow('A/C No',  '<span style="font-family:monospace">' . $this->h($biz['bank_account_no']) . '</span>');
            if (!empty($biz['bank_ifsc']))        $rows .= $this->bankRow('IFSC',   '<span style="font-family:monospace">' . $this->h($biz['bank_ifsc']) . '</span>');
            if (!empty($biz['upi_id']))           $rows .= $this->bankRow('UPI ID', '<span style="font-family:monospace">' . $this->h($biz['upi_id']) . '</span>');

            $qrHtml = '';
            if ($qrSrc && (float)($inv['amount_due'] ?? 0) > 0) {
                $qrHtml = '<td style="width:90px;text-align:center;vertical-align:middle;padding-left:12px">'
                        . '<img src="' . $qrSrc . '" style="width:78px;height:78px;border:1px solid ' . self::BORDER . ';border-radius:6px" />'
                        . '<div style="font-size:9px;color:' . self::MUTED . ';margin-top:3px">Scan to Pay</div>'
                        . '</td>';
            }

            $bankHtml = '<div style="margin-bottom:0">'
                . '<div style="font-size:10px;font-weight:700;color:' . self::ACCENT . ';text-transform:uppercase;letter-spacing:0.08em;margin-bottom:8px">Payment Details</div>'
                . '<table style="width:100%"><tr>'
                . '<td style="vertical-align:top"><table style="width:100%;font-size:12px">' . $rows . '</table></td>'
                . $qrHtml
                . '</tr></table>'
                . '</div>';
        }

        // ── Notes + Terms ─────────────────────────────────────────────────────
        $notesHtml = '';
        $notes = $inv['notes'] ?? $biz['invoice_notes'] ?? '';
        $terms = $inv['terms'] ?? $biz['invoice_terms'] ?? '';
        if ($notes) $notesHtml .= '<div style="margin-bottom:10px"><div style="font-size:10px;font-weight:700;color:' . self::ACCENT . ';text-transform:uppercase;letter-spacing:0.08em;margin-bottom:4px">Notes</div><div style="font-size:11px;color:#475569;line-height:1.5">' . $this->h($notes) . '</div></div>';
        if ($terms) $notesHtml .= '<div><div style="font-size:10px;font-weight:700;color:' . self::ACCENT . ';text-transform:uppercase;letter-spacing:0.08em;margin-bottom:4px">Terms &amp; Conditions</div><div style="font-size:11px;color:#475569;line-height:1.5">' . $this->h($terms) . '</div></div>';

        // ── Logo HTML ─────────────────────────────────────────────────────────
        $logoHtml = $logoSrc
            ? '<img src="' . $logoSrc . '" style="width:52px;height:52px;object-fit:contain;border-radius:8px;border:1px solid rgba(255,255,255,0.2)" alt="logo" />'
            : '<div style="width:44px;height:44px;background:rgba(255,255,255,0.15);border-radius:8px;display:flex;align-items:center;justify-content:center">'
              . '<div style="color:white;font-size:18px;font-weight:900">' . mb_strtoupper(mb_substr($biz['name'] ?? 'B', 0, 1)) . '</div>'
              . '</div>';

        $taxTh = $isGst ? '<th style="padding:10px 10px;text-align:right;font-size:10px;font-weight:700;color:#94a3b8;letter-spacing:0.05em;text-transform:uppercase">Tax</th>' : '';

        return '<!DOCTYPE html>
<html lang="en">
<head>
<meta http-equiv="Content-Type" content="text/html; charset=UTF-8">
<title>' . $this->h($title) . ' &mdash; ' . $this->h($inv['number'] ?? '') . '</title>
<style>
  * { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: DejaVu Sans, sans-serif; background: #f1f5f9; color: ' . self::DARK . '; font-size: 13px; }
  .page { max-width: 780px; margin: 0 auto; background: white; }
  table { width: 100%; border-collapse: collapse; }
  td, th { vertical-align: top; }
</style>
</head>
<body>
<div class="page">

  <!-- ═══ HEADER BAND ══════════════════════════════════════════════════════ -->
  <div style="background:' . self::NAVY . ';padding:0">

    <!-- Top strip with invoice type + number -->
    <div style="background:' . self::ACCENT . ';padding:10px 28px;display:flex;justify-content:space-between;align-items:center">
      <div style="font-size:11px;font-weight:700;color:rgba(255,255,255,0.85);text-transform:uppercase;letter-spacing:0.15em">' . strtoupper($title) . '</div>
      <div style="font-size:13px;font-weight:700;color:white;font-family:monospace">' . $this->h($inv['number'] ?? '') . '</div>
    </div>

    <!-- Business info row -->
    <div style="padding:20px 28px 22px;display:flex;align-items:center;gap:16px">
      ' . $logoHtml . '
      <div style="flex:1">
        <div style="font-size:17px;font-weight:900;color:white;margin-bottom:3px">' . $bizName . '</div>
        <div style="font-size:11px;color:rgba(255,255,255,0.65);line-height:1.6">
          ' . $this->when($bizAddr    !== '', $this->h($bizAddr) . '<br>') . '
          ' . $this->when($bizCity    !== '', $this->h($bizCity) . '<br>') . '
          ' . $this->when($bizContact !== '', $bizContact . '<br>') . '
          ' . $this->when($bizGstin   !== '', 'GSTIN: <span style="font-family:monospace">' . $bizGstin . '</span>') . '
        </div>
      </div>
    </div>
  </div>

  <!-- ═══ BILL TO + INVOICE DETAILS ════════════════════════════════════════ -->
  <table style="border-bottom:2px solid ' . self::BORDER . '">
    <tr>
      <!-- Bill To -->
      <td style="width:50%;padding:20px 28px;border-right:1px solid ' . self::BORDER . '">
        <div style="font-size:10px;font-weight:700;color:' . self::ACCENT . ';text-transform:uppercase;letter-spacing:0.1em;margin-bottom:8px">Bill To</div>
        <div style="font-size:14px;font-weight:800;color:' . self::NAVY . ';margin-bottom:3px">' . $this->h($inv['client_name'] ?? '') . '</div>
        ' . $this->when(!empty($inv['client_company']),  '<div style="font-size:12px;color:#475569;margin-bottom:2px">' . $this->h($inv['client_company'] ?? '') . '</div>') . '
        ' . $this->when(!empty($inv['client_address1']), '<div style="font-size:11px;color:' . self::MUTED . ';margin-bottom:1px">' . $clientAddr . '</div>') . '
        ' . $this->when($clientCity !== '',              '<div style="font-size:11px;color:' . self::MUTED . ';margin-bottom:1px">' . $clientCity . '</div>') . '
        ' . $this->when(!empty($inv['client_gstin']),    '<div style="font-size:11px;color:' . self::MUTED . ';font-family:monospace;margin-top:4px">GSTIN: ' . $this->h($inv['client_gstin'] ?? '') . '</div>') . '
        ' . $this->when(!empty($inv['client_mobile']),   '<div style="font-size:11px;color:' . self::MUTED . ';margin-top:2px">' . $this->h($inv['client_mobile'] ?? '') . '</div>') . '
      </td>

      <!-- Invoice Details -->
      <td style="width:50%;padding:20px 28px">
        <div style="font-size:10px;font-weight:700;color:' . self::ACCENT . ';text-transform:uppercase;letter-spacing:0.1em;margin-bottom:8px">Invoice Details</div>
        <table style="font-size:12px">
          <tr><td style="color:' . self::MUTED . ';padding-bottom:6px;padding-right:16px;white-space:nowrap">Invoice No</td>     <td style="font-weight:700;color:' . self::NAVY . ';font-family:monospace">' . $this->h($inv['number'] ?? '') . '</td></tr>
          <tr><td style="color:' . self::MUTED . ';padding-bottom:6px;padding-right:16px">Invoice Date</td>  <td style="color:' . self::DARK . '">' . $this->fmtDate($inv['issue_date'] ?? null) . '</td></tr>
          <tr><td style="color:' . self::MUTED . ';padding-bottom:6px;padding-right:16px">Due Date</td>      <td style="color:' . self::DARK . ';font-weight:600">' . $this->fmtDate($inv['due_date'] ?? null) . '</td></tr>
          <tr><td style="color:' . self::MUTED . ';padding-right:16px;white-space:nowrap">Place of Supply</td><td style="color:' . self::DARK . '">' . $this->h($inv['place_of_supply_name'] ?? $inv['supply_type'] ?? '') . '</td></tr>
        </table>
      </td>
    </tr>
  </table>

  <!-- ═══ ITEMS TABLE ══════════════════════════════════════════════════════ -->
  <div style="padding:0 0">
    <table>
      <thead>
        <tr style="background:' . self::NAVY . '">
          <th style="padding:10px 10px;text-align:center;font-size:10px;font-weight:700;color:#94a3b8;letter-spacing:0.05em;width:32px">#</th>
          <th style="padding:10px 10px;text-align:left;font-size:10px;font-weight:700;color:#94a3b8;letter-spacing:0.05em;text-transform:uppercase">Description</th>
          <th style="padding:10px 10px;text-align:center;font-size:10px;font-weight:700;color:#94a3b8;letter-spacing:0.05em;text-transform:uppercase">HSN/SAC</th>
          <th style="padding:10px 10px;text-align:right;font-size:10px;font-weight:700;color:#94a3b8;letter-spacing:0.05em;text-transform:uppercase">Qty</th>
          <th style="padding:10px 10px;text-align:right;font-size:10px;font-weight:700;color:#94a3b8;letter-spacing:0.05em;text-transform:uppercase">Rate</th>
          <th style="padding:10px 10px;text-align:right;font-size:10px;font-weight:700;color:#94a3b8;letter-spacing:0.05em;text-transform:uppercase">Taxable</th>
          ' . $taxTh . '
          <th style="padding:10px 10px;text-align:right;font-size:10px;font-weight:700;color:#94a3b8;letter-spacing:0.05em;text-transform:uppercase">Amount</th>
        </tr>
      </thead>
      <tbody>' . $itemsHtml . '</tbody>
    </table>
  </div>

  <!-- ═══ TOTALS + WORDS ════════════════════════════════════════════════════ -->
  <div style="border-top:2px solid ' . self::BORDER . ';padding:20px 28px">
    <table>
      <tr>
        <!-- Amount in Words -->
        <td style="vertical-align:middle;padding-right:24px">
          <div style="font-size:10px;font-weight:700;color:' . self::ACCENT . ';text-transform:uppercase;letter-spacing:0.08em;margin-bottom:6px">Amount in Words</div>
          <div style="font-size:12px;color:#475569;font-style:italic;line-height:1.5">' . $this->h($this->amountInWords($inv['total'] ?? 0)) . '</div>
        </td>

        <!-- Totals table -->
        <td style="width:230px;vertical-align:top">
          <table style="border-radius:8px;overflow:hidden;border:1px solid ' . self::BORDER . '">
            ' . $totals . '
          </table>
        </td>
      </tr>
    </table>
  </div>

  <!-- ═══ PAYMENT + NOTES ══════════════════════════════════════════════════ -->
  ' . ($hasBank || $notesHtml ? '
  <div style="border-top:1px solid ' . self::BORDER . ';padding:20px 28px;background:#f8fafc">
    <table>
      <tr>
        <td style="width:50%;padding-right:24px;vertical-align:top">' . $bankHtml . '</td>
        <td style="width:50%;vertical-align:top">' . $notesHtml . '</td>
      </tr>
    </table>
  </div>' : '') . '

  <!-- ═══ SIGNATURE FOOTER ═════════════════════════════════════════════════ -->
  <div style="border-top:1px solid ' . self::BORDER . ';padding:24px 28px 28px">
    <table>
      <tr>
        <td style="width:50%;text-align:center;padding-right:32px">
          <div style="height:44px;border-bottom:1px solid #94a3b8;margin-bottom:8px"></div>
          <div style="font-size:11px;font-weight:600;color:' . self::MUTED . '">Customer Signature</div>
        </td>
        <td style="width:50%;text-align:center;padding-left:32px">
          <div style="height:44px;border-bottom:1px solid #94a3b8;margin-bottom:8px"></div>
          <div style="font-size:11px;font-weight:600;color:' . self::MUTED . '">Authorised Signatory</div>
          <div style="font-size:10px;color:' . self::MUTED . ';margin-top:2px;font-style:italic">For ' . $bizName . '</div>
        </td>
      </tr>
    </table>
  </div>

  <!-- ═══ BOTTOM STRIP ══════════════════════════════════════════════════════ -->
  <div style="background:' . self::NAVY . ';padding:8px 28px;display:flex;justify-content:space-between;align-items:center">
    <div style="font-size:10px;color:rgba(255,255,255,0.4)">' . $this->h($inv['number'] ?? '') . '</div>
    <div style="font-size:10px;color:rgba(255,255,255,0.4)">Generated by CloudBill</div>
  </div>

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
