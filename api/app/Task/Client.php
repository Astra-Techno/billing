<?php

namespace App\Task;

use App\Base\Task;
use App\Core\DB;
use App\Tables\Client as ClientTable;
use App\Tables\ClientContact;

class Client extends Task
{
    // ── Create client ─────────────────────────────────────────────────────────

    public function create(array $input): array
    {
        $this->validate([
            'name'  => 'required|string|min_length:2',
            'type'  => 'required|in:business,individual',
        ]);

        $businessId = $this->requireBusiness();

        // GSTIN validation if provided
        if (!empty($input['gstin'])) {
            $gstin = strtoupper(trim($input['gstin']));
            if (!preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/', $gstin)) {
                $this->fail('Invalid GSTIN format.');
            }
            $input['gstin'] = $gstin;
        }

        $client = ClientTable::create([
            'business_id'  => $businessId,
            'type'         => $input['type'],
            'name'         => trim($input['name']),
            'company'      => $input['company']      ?? null,
            'gstin'        => $input['gstin']        ?? null,
            'pan'          => $input['pan']          ?? null,
            'email'        => $input['email']        ?? null,
            'mobile'       => $input['mobile']       ?? null,
            'phone'        => $input['phone']        ?? null,
            'address_line1'=> $input['address_line1'] ?? null,
            'address_line2'=> $input['address_line2'] ?? null,
            'city'         => $input['city']         ?? null,
            'state_id'     => $input['state_id']     ?? null,
            'pincode'      => $input['pincode']      ?? null,
            'credit_limit' => $input['credit_limit'] ?? null,
            'credit_days'  => $input['credit_days']  ?? 30,
            'notes'        => $input['notes']        ?? null,
            'active'       => 1,
        ]);

        // Add primary contact if contact info provided
        if (!empty($input['contact_name'])) {
            ClientContact::create([
                'client_id'   => $client->id,
                'business_id' => $businessId,
                'name'        => $input['contact_name'],
                'designation' => $input['contact_designation'] ?? null,
                'email'       => $input['contact_email']       ?? $input['email'] ?? null,
                'mobile'      => $input['contact_mobile']      ?? $input['mobile'] ?? null,
                'whatsapp'    => $input['contact_whatsapp']    ?? $input['mobile'] ?? null,
                'is_primary'  => 1,
                'send_invoice'=> 1,
                'active'      => 1,
            ]);
        }

        return $this->success(['client_id' => $client->id], 'Client added successfully.');
    }

    // ── Update client ─────────────────────────────────────────────────────────

    public function update(array $input): array
    {
        $this->validate([
            'id'   => 'required|integer',
            'name' => 'required|string|min_length:2',
        ]);

        $businessId = $this->requireBusiness();
        $client     = $this->findClient((int)$input['id'], $businessId);

        if (!empty($input['gstin'])) {
            $gstin = strtoupper(trim($input['gstin']));
            if (!preg_match('/^[0-9]{2}[A-Z]{5}[0-9]{4}[A-Z]{1}[1-9A-Z]{1}Z[0-9A-Z]{1}$/', $gstin)) {
                $this->fail('Invalid GSTIN format.');
            }
            $input['gstin'] = $gstin;
        }

        $client->fill([
            'type'         => $input['type']          ?? $client->type,
            'name'         => trim($input['name']),
            'company'      => $input['company']       ?? $client->company,
            'gstin'        => $input['gstin']         ?? $client->gstin,
            'pan'          => $input['pan']           ?? $client->pan,
            'email'        => $input['email']         ?? $client->email,
            'mobile'       => $input['mobile']        ?? $client->mobile,
            'phone'        => $input['phone']         ?? $client->phone,
            'address_line1'=> $input['address_line1'] ?? $client->address_line1,
            'address_line2'=> $input['address_line2'] ?? $client->address_line2,
            'city'         => $input['city']          ?? $client->city,
            'state_id'     => $input['state_id']      ?? $client->state_id,
            'pincode'      => $input['pincode']       ?? $client->pincode,
            'credit_limit' => $input['credit_limit']  ?? $client->credit_limit,
            'credit_days'  => $input['credit_days']   ?? $client->credit_days,
            'notes'        => $input['notes']         ?? $client->notes,
        ]);
        $client->save();

        return $this->success(['client_id' => $client->id], 'Client updated.');
    }

    // ── Soft delete client ────────────────────────────────────────────────────

    public function delete(array $input): array
    {
        $this->validate(['id' => 'required|integer']);

        $businessId = $this->requireBusiness();
        $client     = $this->findClient((int)$input['id'], $businessId);

        // Check for open invoices
        $open = DB::selectOne(
            "SELECT COUNT(*) AS cnt FROM invoices
             WHERE client_id = ? AND business_id = ? AND status NOT IN ('cancelled','paid')",
            [$client->id, $businessId]
        );
        if ((int)($open->cnt ?? 0) > 0) {
            $this->fail('Cannot delete client with open invoices. Close or cancel them first.');
        }

        $client->setAttribute('active', 0);
        $client->save();

        return $this->success(null, 'Client deactivated.');
    }

    // ── Add contact ───────────────────────────────────────────────────────────

    public function addContact(array $input): array
    {
        $this->validate([
            'client_id' => 'required|integer',
            'name'      => 'required|string|min_length:2',
        ]);

        $businessId = $this->requireBusiness();
        $client     = $this->findClient((int)$input['client_id'], $businessId);

        // If marking as primary, clear existing primary
        if (!empty($input['is_primary'])) {
            DB::statement(
                "UPDATE client_contacts SET is_primary = 0 WHERE client_id = ?",
                [$client->id]
            );
        }

        $contact = ClientContact::create([
            'client_id'   => $client->id,
            'business_id' => $businessId,
            'name'        => trim($input['name']),
            'designation' => $input['designation']  ?? null,
            'email'       => $input['email']        ?? null,
            'mobile'      => $input['mobile']       ?? null,
            'phone'       => $input['phone']        ?? null,
            'whatsapp'    => $input['whatsapp']     ?? $input['mobile'] ?? null,
            'is_primary'  => !empty($input['is_primary']) ? 1 : 0,
            'send_invoice'=> isset($input['send_invoice']) ? (int)$input['send_invoice'] : 1,
            'notes'       => $input['notes']        ?? null,
            'active'      => 1,
        ]);

        return $this->success(['contact_id' => $contact->id], 'Contact added.');
    }

    // ── Update contact ────────────────────────────────────────────────────────

    public function updateContact(array $input): array
    {
        $this->validate([
            'id'   => 'required|integer',
            'name' => 'required|string',
        ]);

        $businessId = $this->requireBusiness();
        $contact    = $this->findContact((int)$input['id'], $businessId);

        if (!empty($input['is_primary'])) {
            DB::statement(
                "UPDATE client_contacts SET is_primary = 0 WHERE client_id = ?",
                [$contact->client_id]
            );
        }

        $contact->fill([
            'name'        => trim($input['name']),
            'designation' => $input['designation']  ?? $contact->designation,
            'email'       => $input['email']        ?? $contact->email,
            'mobile'      => $input['mobile']       ?? $contact->mobile,
            'phone'       => $input['phone']        ?? $contact->phone,
            'whatsapp'    => $input['whatsapp']     ?? $contact->whatsapp,
            'is_primary'  => !empty($input['is_primary']) ? 1 : (int)$contact->is_primary,
            'send_invoice'=> isset($input['send_invoice']) ? (int)$input['send_invoice'] : (int)$contact->send_invoice,
            'notes'       => $input['notes']        ?? $contact->notes,
        ]);
        $contact->save();

        return $this->success(null, 'Contact updated.');
    }

    // ── Delete contact ────────────────────────────────────────────────────────

    public function deleteContact(array $input): array
    {
        $this->validate(['id' => 'required|integer']);

        $businessId = $this->requireBusiness();
        $contact    = $this->findContact((int)$input['id'], $businessId);

        DB::statement("DELETE FROM client_contacts WHERE id = ?", [$contact->id]);

        return $this->success(null, 'Contact removed.');
    }

    // ── Generate portal access token ──────────────────────────────────────────

    public function generatePortalToken(array $input): array
    {
        $this->validate(['id' => 'required|integer']);

        $businessId = $this->requireBusiness();
        $client     = $this->findClient((int)$input['id'], $businessId);

        $token = bin2hex(random_bytes(24));
        $client->setAttribute('portal_token', $token);
        $client->save();

        return $this->success(['portal_token' => $token], 'Portal link generated.');
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function findClient(int $id, int $businessId): object
    {
        $client = ClientTable::find($id);
        if (!$client || (int)$client->business_id !== $businessId)
            $this->fail('Client not found.', 404);
        return $client;
    }

    private function findContact(int $id, int $businessId): object
    {
        $contact = ClientContact::find($id);
        if (!$contact || (int)$contact->business_id !== $businessId)
            $this->fail('Contact not found.', 404);
        return $contact;
    }
}
