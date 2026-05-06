<?php

namespace App\Task;

use App\Base\Task;
use App\Core\DB;
use App\Tables\Expense as ExpenseTable;
use App\Tables\ExpenseCategory;

class Expense extends Task
{
    // ── Create expense ────────────────────────────────────────────────────────

    public function create(array $input): array
    {
        $this->validate([
            'description'  => 'required|string',
            'total_amount' => 'required|numeric',
            'expense_date' => 'required|date',
        ]);

        $businessId = $this->requireBusiness();
        $total      = (float)$input['total_amount'];
        $gstAmt     = (float)($input['gst_amount'] ?? 0);
        $amount     = round($total - $gstAmt, 2);

        ExpenseTable::create([
            'business_id'  => $businessId,
            'category_id'  => !empty($input['category_id']) ? (int)$input['category_id'] : null,
            'recorded_by'  => $this->userId(),
            'vendor_name'  => $input['vendor_name']  ?? null,
            'description'  => trim($input['description']),
            'amount'       => $amount,
            'gst_amount'   => $gstAmt,
            'total_amount' => $total,
            'expense_date' => $input['expense_date'],
            'method'       => $input['method']     ?? 'cash',
            'reference'    => $input['reference']  ?? null,
            'financial_year'=> Sequence::currentFinancialYear(),
            'notes'        => $input['notes']      ?? null,
        ]);

        return $this->success(null, 'Expense recorded.');
    }

    // ── Update expense ────────────────────────────────────────────────────────

    public function update(array $input): array
    {
        $this->validate([
            'id'           => 'required|integer',
            'description'  => 'required|string',
            'total_amount' => 'required|numeric',
            'expense_date' => 'required|date',
        ]);

        $businessId = $this->requireBusiness();
        $expense    = $this->findExpense((int)$input['id'], $businessId);

        $total  = (float)$input['total_amount'];
        $gstAmt = (float)($input['gst_amount'] ?? 0);

        $expense->fill([
            'category_id'  => !empty($input['category_id']) ? (int)$input['category_id'] : $expense->category_id,
            'vendor_name'  => $input['vendor_name']  ?? $expense->vendor_name,
            'description'  => trim($input['description']),
            'amount'       => round($total - $gstAmt, 2),
            'gst_amount'   => $gstAmt,
            'total_amount' => $total,
            'expense_date' => $input['expense_date'],
            'method'       => $input['method']     ?? $expense->method,
            'reference'    => $input['reference']  ?? $expense->reference,
            'notes'        => $input['notes']      ?? $expense->notes,
        ]);
        $expense->save();

        return $this->success(null, 'Expense updated.');
    }

    // ── Delete expense ────────────────────────────────────────────────────────

    public function delete(array $input): array
    {
        $this->validate(['id' => 'required|integer']);

        $businessId = $this->requireBusiness();
        $this->requireRole(['owner', 'admin']);
        $expense    = $this->findExpense((int)$input['id'], $businessId);

        DB::statement("DELETE FROM expenses WHERE id = ?", [$expense->id]);

        return $this->success(null, 'Expense deleted.');
    }

    // ── Expense categories ────────────────────────────────────────────────────

    public function addCategory(array $input): array
    {
        $this->validate(['name' => 'required|string|min_length:2']);

        $businessId = $this->requireBusiness();

        $cat = ExpenseCategory::create([
            'business_id' => $businessId,
            'name'        => trim($input['name']),
            'sort_order'  => (int)($input['sort_order'] ?? 99),
        ]);

        return $this->success(['category_id' => $cat->id], 'Category added.');
    }

    public function deleteCategory(array $input): array
    {
        $this->validate(['id' => 'required|integer']);

        $businessId = $this->requireBusiness();
        $this->requireRole(['owner', 'admin']);
        $cat        = ExpenseCategory::find((int)$input['id']);

        if (!$cat || (int)$cat->business_id !== $businessId) $this->fail('Category not found.', 404);

        // Move expenses to uncategorized
        DB::statement(
            "UPDATE expenses SET category_id = NULL WHERE category_id = ?",
            [$cat->id]
        );
        DB::statement("DELETE FROM expense_categories WHERE id = ?", [$cat->id]);

        return $this->success(null, 'Category deleted.');
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function findExpense(int $id, int $businessId): object
    {
        $expense = ExpenseTable::find($id);
        if (!$expense || (int)$expense->business_id !== $businessId)
            $this->fail('Expense not found.', 404);
        return $expense;
    }
}
