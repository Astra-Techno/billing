<?php

namespace App\Task;

use App\Base\Task;
use App\Core\DB;
use App\Tables\Business;
use App\Tables\EwayBill as EwayBillTable;
use App\Tables\Invoice;

class EwayBill extends Task
{
    protected bool $useTransaction = true;

    // ── Create ────────────────────────────────────────────────────────────────

    public function create(array $input): array
    {
        $this->validate([
            'invoice_id' => 'required|integer',
            'mode'       => 'required|string',
            'distance'   => 'required|numeric',
            'vehicle_no' => 'required|string',
        ]);

        $businessId = $this->requireBusiness();

        $invoice = Invoice::find((int)$input['invoice_id']);
        if (!$invoice || (int)$invoice->business_id !== $businessId) {
            $this->fail('Invoice not found.', 404);
        }

        $existing = DB::selectOne(
            'SELECT id FROM eway_bills WHERE invoice_id = ? AND status = ? LIMIT 1',
            [(int)$invoice->id, 'active']
        );
        if ($existing) {
            $this->fail('An active E-way Bill already exists for this invoice.');
        }

        $distance   = (float)$input['distance'];
        $validDays  = $this->validityDays($input['mode'], $distance);
        $validFrom  = date('Y-m-d H:i:s');
        $validUntil = date('Y-m-d 23:59:00', strtotime("+{$validDays} days"));

        $ewbNumber = $this->generateEwbNumber($businessId, $invoice, $input);

        $ewb = EwayBillTable::create([
            'business_id'  => $businessId,
            'created_by'   => $this->userId(),
            'invoice_id'   => (int)$invoice->id,
            'ewb_number'   => $ewbNumber,
            'status'       => 'active',
            'mode'         => $input['mode'],
            'distance'     => $distance,
            'vehicle_no'   => strtoupper(trim($input['vehicle_no'])),
            'vehicle_type' => $input['vehicle_type'] ?? 'Regular',
            'transporter'  => !empty($input['transporter']) ? trim($input['transporter']) : null,
            'valid_from'   => $validFrom,
            'valid_until'  => $validUntil,
        ]);

        return $this->success([
            'id'           => $ewb->id,
            'ewb_number'   => $ewbNumber,
            'status'       => 'active',
            'mode'         => $ewb->mode,
            'distance'     => $ewb->distance,
            'vehicle_no'   => $ewb->vehicle_no,
            'vehicle_type' => $ewb->vehicle_type,
            'transporter'  => $ewb->transporter,
            'valid_from'   => $validFrom,
            'valid_until'  => $validUntil,
            'created_at'   => $ewb->created_at,
        ], 'E-way Bill generated successfully.');
    }

    // ── Cancel ────────────────────────────────────────────────────────────────

    public function cancel(array $input): array
    {
        $this->validate(['id' => 'required|integer']);

        $businessId = $this->requireBusiness();
        $ewb = EwayBillTable::find((int)$input['id']);

        if (!$ewb || (int)$ewb->business_id !== $businessId) {
            $this->fail('E-way Bill not found.', 404);
        }

        if ($ewb->status !== 'active') {
            $this->fail('Only active E-way Bills can be cancelled.');
        }

        $ewb->fill(['status' => 'cancelled']);
        $ewb->save();

        return $this->success(['id' => $ewb->id], 'E-way Bill cancelled.');
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    private function validityDays(string $mode, float $distance): int
    {
        if ($mode !== 'road') return 15;
        if ($distance <= 100)  return 1;
        if ($distance <= 300)  return 3;
        if ($distance <= 500)  return 5;
        if ($distance <= 1000) return 7;
        return 15;
    }

    // ── EWB generation (GSP or simulation) ───────────────────────────────────

    private function generateEwbNumber(int $businessId, object $invoice, array $input): string
    {
        // Platform-level GSP app credentials (.env)
        $gspUrl       = $_ENV['EWB_GSP_URL']       ?? '';
        $clientId     = $_ENV['EWB_CLIENT_ID']     ?? '';
        $clientSecret = $_ENV['EWB_CLIENT_SECRET'] ?? '';

        // Per-business NIC portal credentials
        $business = Business::find($businessId);
        $ewbUser  = $business->ewb_username ?? '';
        $ewbPass  = $business->ewb_password ?? '';
        $gstin    = $business->gstin        ?? '';

        if ($gspUrl && $clientId && $clientSecret && $ewbUser && $ewbPass && $gstin) {
            return $this->callGspApi(
                $gspUrl, $clientId, $clientSecret,
                $gstin, $ewbUser, $ewbPass,
                $business, $invoice, $input
            );
        }

        // Simulation fallback — generates a realistic 12-digit number like NIC returns
        // Prefix 99 marks it as a test/simulation number (NIC real numbers start with 24/25 etc.)
        $suffix = str_pad((string)random_int(0, 9999999999), 10, '0', STR_PAD_LEFT);
        return '99' . $suffix;
    }

    // ── GSP API call ─────────────────────────────────────────────────────────

    private function callGspApi(
        string $gspUrl, string $clientId, string $clientSecret,
        string $gstin, string $ewbUser, string $ewbPass,
        object $business, object $invoice, array $input
    ): string {
        // 1. Authenticate — get access token
        $tokenRes = $this->httpPost(
            rtrim($gspUrl, '/') . '?action=ACCESSTOKEN',
            ['action' => 'ACCESSTOKEN', 'username' => $ewbUser, 'password' => $ewbPass, 'gstin' => $gstin],
            ['client_id' => $clientId, 'client_secret' => $clientSecret]
        );

        $token = $tokenRes['authToken'] ?? null;
        if (!$token) {
            $this->fail('EWB GSP authentication failed: ' . ($tokenRes['error'] ?? json_encode($tokenRes)));
        }

        // 2. Build Part A + B payload
        $payload = $this->buildPayload($gstin, $business, $invoice, $input);

        // 3. Generate EWB
        $ewbRes = $this->httpPost(
            rtrim($gspUrl, '/') . '?action=GENEWAYBILL',
            $payload,
            [
                'client_id'     => $clientId,
                'client_secret' => $clientSecret,
                'authtoken'     => $token,
                'Gstin'         => $gstin,
            ]
        );

        $ewbNo = $ewbRes['ewbNo'] ?? null;
        if (!$ewbNo) {
            $err = $ewbRes['error'] ?? ($ewbRes['message'] ?? json_encode($ewbRes));
            $this->fail('EWB generation failed: ' . $err);
        }

        return (string)$ewbNo;
    }

    // ── Part A + B payload builder ────────────────────────────────────────────

    private function buildPayload(
        string $gstin, object $business, object $invoice, array $input
    ): array {
        // Fetch client
        $client = DB::selectOne(
            'SELECT c.*, s.code AS state_code
             FROM clients c
             LEFT JOIN indian_states s ON s.id = c.state_id
             WHERE c.id = ?',
            [(int)$invoice->client_id]
        );

        // Fetch seller state code
        $fromState = DB::selectOne(
            'SELECT code FROM indian_states WHERE id = ?',
            [(int)$business->state_id]
        );

        // Fetch invoice items
        $items = DB::select(
            'SELECT * FROM invoice_items WHERE invoice_id = ? ORDER BY sort_order',
            [(int)$invoice->id]
        );

        $transMode   = ['road' => '1', 'rail' => '2', 'air' => '3', 'ship' => '4'][$input['mode']] ?? '1';
        $vehicleType = ($input['vehicle_type'] ?? 'Regular') === 'Regular' ? 'R' : 'O';
        $toGstin     = !empty($client->gstin) ? strtoupper($client->gstin) : 'URP';
        $fromCode    = (int)($fromState->code ?? 0);
        $toCode      = (int)($client->state_code ?? $fromCode);

        return [
            // ── Part A: Document details ──────────────────────────
            'supplyType'        => 'O',      // Outward supply
            'subSupplyType'     => '1',      // Supply
            'docType'           => 'INV',
            'docNo'             => $invoice->number,
            'docDate'           => date('d/m/Y', strtotime($invoice->issue_date)),

            // ── From (Supplier / Seller) ──────────────────────────
            'fromGstin'         => $gstin,
            'fromTrdName'       => $business->name,
            'fromAddr1'         => $business->address_line1 ?? '',
            'fromAddr2'         => $business->address_line2 ?? '',
            'fromPlace'         => $business->city          ?? '',
            'fromPincode'       => (int)($business->pincode ?? 0),
            'fromStateCode'     => $fromCode,
            'actFromStateCode'  => $fromCode,

            // ── To (Buyer / Consignee) ────────────────────────────
            'toGstin'           => $toGstin,
            'toTrdName'         => $client->company ?: $client->name,
            'toAddr1'           => $client->address_line1 ?? '',
            'toAddr2'           => $client->address_line2 ?? '',
            'toPlace'           => $client->city           ?? '',
            'toPincode'         => (int)($client->pincode  ?? 0),
            'toStateCode'       => $toCode,
            'actToStateCode'    => $toCode,

            // ── Value summary ─────────────────────────────────────
            'totalValue'        => (float)$invoice->subtotal,
            'cgstValue'         => (float)$invoice->cgst_total,
            'sgstValue'         => (float)$invoice->sgst_total,
            'igstValue'         => (float)$invoice->igst_total,
            'cessValue'         => 0,
            'cessNonAdvalValue' => 0,
            'otherValue'        => (float)$invoice->round_off,
            'totInvValue'       => (float)$invoice->total,

            // ── Part B: Transport details ─────────────────────────
            'transMode'         => $transMode,
            'transDistance'     => (int)$input['distance'],
            'transporterName'   => $input['transporter'] ?? '',
            'transporterId'     => '',
            'transDocNo'        => '',
            'transDocDate'      => '',
            'vehicleNo'         => strtoupper(trim($input['vehicle_no'])),
            'vehicleType'       => $vehicleType,

            // ── Item list ─────────────────────────────────────────
            'itemList'          => array_values(array_map(
                fn($item, $idx) => [
                    'itemNo'        => $idx + 1,
                    'productName'   => mb_substr($item->description, 0, 50),
                    'productDesc'   => mb_substr($item->description, 0, 100),
                    'hsnCode'       => $item->hsn_sac  ?? '',
                    'quantity'      => (float)$item->quantity,
                    'qtyUnit'       => strtoupper($item->unit ?? 'NOS'),
                    'taxableAmount' => (float)$item->taxable_amt,
                    'cgstRate'      => (float)$item->cgst_rate,
                    'sgstRate'      => (float)$item->sgst_rate,
                    'igstRate'      => (float)$item->igst_rate,
                    'cessRate'      => 0,
                ],
                $items, array_keys($items)
            )),
        ];
    }

    // ── HTTP helper ───────────────────────────────────────────────────────────

    private function httpPost(string $url, array $body, array $headers = []): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_POST           => true,
            CURLOPT_POSTFIELDS     => json_encode($body),
            CURLOPT_HTTPHEADER     => array_merge(
                ['Content-Type: application/json', 'Accept: application/json'],
                array_map(fn($k, $v) => "$k: $v", array_keys($headers), array_values($headers))
            ),
            CURLOPT_TIMEOUT        => 15,
            CURLOPT_SSL_VERIFYPEER => true,
        ]);
        $response = curl_exec($ch);
        $err      = curl_error($ch);
        curl_close($ch); // @phpstan-ignore-line (deprecated in PHP 8.0 but harmless)

        if ($err) $this->fail('GSP connection error: ' . $err);

        return json_decode($response ?: '{}', true) ?? [];
    }
}
