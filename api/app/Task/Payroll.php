<?php

namespace App\Task;

use App\Base\Task;
use App\Core\DB;
use App\Tables\PayrollRun;
use App\Tables\Expense as ExpenseTable;
use App\Tables\ExpenseCategory;

class Payroll extends Task
{
    protected bool $useTransaction = true;

    // ── Generate payroll for a month ──────────────────────────────────────────

    public function generate(array $input): array
    {
        $this->validate([
            'month' => 'required|integer',
            'year'  => 'required|integer',
        ]);

        $businessId   = $this->requireBusiness();
        $month        = (int)$input['month'];
        $year         = (int)$input['year'];
        $workingDays  = (int)($input['working_days'] ?? 26);

        $staffList = DB::select(
            "SELECT id, name, monthly_salary FROM staff_members
             WHERE business_id = ? AND is_active = 1
             ORDER BY name",
            [$businessId]
        );

        if (empty($staffList)) {
            $this->fail('No active staff members found for this business.');
        }

        $generated = [];

        foreach ($staffList as $staff) {
            $basicSalary = (float)$staff->monthly_salary;
            $daysWorked  = $workingDays; // default: full month
            $bonus       = 0.0;
            $deductions  = 0.0;
            $netPay      = round(($basicSalary * $daysWorked / $workingDays) + $bonus - $deductions, 2);

            // Check if a draft record already exists for this staff/month/year
            $existing = DB::selectOne(
                "SELECT id FROM payroll_runs
                 WHERE business_id = ? AND staff_id = ? AND month = ? AND year = ? AND status = 'draft'",
                [$businessId, $staff->id, $month, $year]
            );

            if ($existing) {
                // Update the existing draft
                DB::statement(
                    "UPDATE payroll_runs
                     SET basic_salary = ?, days_worked = ?, working_days = ?,
                         bonus = ?, deductions = ?, net_pay = ?
                     WHERE id = ?",
                    [$basicSalary, $daysWorked, $workingDays, $bonus, $deductions, $netPay, $existing->id]
                );
                $runId = (int)$existing->id;
            } else {
                $run = PayrollRun::create([
                    'business_id' => $businessId,
                    'staff_id'    => $staff->id,
                    'month'       => $month,
                    'year'        => $year,
                    'basic_salary'=> $basicSalary,
                    'days_worked' => $daysWorked,
                    'working_days'=> $workingDays,
                    'bonus'       => $bonus,
                    'deductions'  => $deductions,
                    'net_pay'     => $netPay,
                    'status'      => 'draft',
                ]);
                $runId = (int)$run->id;
            }

            $generated[] = [
                'id'           => $runId,
                'staff_id'     => (int)$staff->id,
                'staff_name'   => $staff->name,
                'basic_salary' => $basicSalary,
                'days_worked'  => $daysWorked,
                'working_days' => $workingDays,
                'bonus'        => $bonus,
                'deductions'   => $deductions,
                'net_pay'      => $netPay,
                'status'       => 'draft',
            ];
        }

        return $this->success([
            'month'     => $month,
            'year'      => $year,
            'count'     => count($generated),
            'records'   => $generated,
        ], 'Payroll generated for ' . count($generated) . ' staff member(s).');
    }

    // ── Update a single payroll run (draft only) ──────────────────────────────

    public function update(array $input): array
    {
        $this->validate(['id' => 'required|integer']);

        $businessId = $this->requireBusiness();
        $run        = $this->findRun((int)$input['id'], $businessId);

        if ($run->status !== 'draft') {
            $this->fail('Only draft payroll records can be updated.');
        }

        $basicSalary = (float)$run->basic_salary;
        $workingDays = (int)$run->working_days;
        $daysWorked  = isset($input['days_worked'])  ? (int)$input['days_worked']    : (int)$run->days_worked;
        $bonus       = isset($input['bonus'])        ? (float)$input['bonus']        : (float)$run->bonus;
        $deductions  = isset($input['deductions'])   ? (float)$input['deductions']   : (float)$run->deductions;
        $netPay      = round(($basicSalary * $daysWorked / $workingDays) + $bonus - $deductions, 2);

        $run->fill([
            'days_worked' => $daysWorked,
            'bonus'       => $bonus,
            'deductions'  => $deductions,
            'net_pay'     => $netPay,
            'note'        => $input['note'] ?? $run->note,
        ]);
        $run->save();

        return $this->success([
            'id'      => (int)$run->id,
            'net_pay' => $netPay,
        ], 'Payroll record updated.');
    }

    // ── Pay a single payroll run ──────────────────────────────────────────────

    public function pay(array $input): array
    {
        $this->validate([
            'id'        => 'required|integer',
            'method'    => 'required|string',
            'paid_date' => 'required|date',
        ]);

        $businessId = $this->requireBusiness();
        $run        = $this->findRun((int)$input['id'], $businessId);

        if ($run->status !== 'draft') {
            $this->fail('Only draft payroll records can be paid.');
        }

        // Fetch staff name for the expense description
        $staff = DB::selectOne(
            "SELECT name FROM staff_members WHERE id = ? AND business_id = ?",
            [$run->staff_id, $businessId]
        );
        $staffName = $staff ? $staff->name : 'Staff';

        // Auto-find or create "Salary" expense category
        $categoryId = $this->getOrCreateSalaryCategory($businessId);

        // Create expense record
        $description = "Salary - {$staffName} - {$run->month}/{$run->year}";
        $expense = ExpenseTable::create([
            'business_id'  => $businessId,
            'category_id'  => $categoryId,
            'recorded_by'  => $this->userId(),
            'description'  => $description,
            'amount'       => (float)$run->net_pay,
            'gst_amount'   => 0,
            'total_amount' => (float)$run->net_pay,
            'expense_date' => $input['paid_date'],
            'method'       => $input['method'],
            'reference'    => $input['reference'] ?? null,
            'notes'        => $run->note ?? null,
        ]);

        // Mark payroll run as paid and link expense
        $run->fill([
            'status'     => 'paid',
            'paid_date'  => $input['paid_date'],
            'method'     => $input['method'],
            'expense_id' => $expense->id,
        ]);
        $run->save();

        return $this->success([
            'id'         => (int)$run->id,
            'expense_id' => (int)$expense->id,
        ], "Salary paid for {$staffName}.");
    }

    // ── Pay all draft runs for a given month/year ─────────────────────────────

    public function payAll(array $input): array
    {
        $this->validate([
            'month'     => 'required|integer',
            'year'      => 'required|integer',
            'method'    => 'required|string',
            'paid_date' => 'required|date',
        ]);

        $businessId = $this->requireBusiness();
        $month      = (int)$input['month'];
        $year       = (int)$input['year'];

        $runs = DB::select(
            "SELECT pr.*, sm.name AS staff_name
             FROM payroll_runs pr
             LEFT JOIN staff_members sm ON sm.id = pr.staff_id
             WHERE pr.business_id = ? AND pr.month = ? AND pr.year = ? AND pr.status = 'draft'
             ORDER BY sm.name",
            [$businessId, $month, $year]
        );

        if (empty($runs)) {
            $this->fail('No draft payroll records found for ' . $month . '/' . $year . '.');
        }

        $categoryId = $this->getOrCreateSalaryCategory($businessId);
        $paid       = [];

        foreach ($runs as $run) {
            $staffName   = $run->staff_name ?? 'Staff';
            $description = "Salary - {$staffName} - {$month}/{$year}";

            $expense = ExpenseTable::create([
                'business_id'  => $businessId,
                'category_id'  => $categoryId,
                'recorded_by'  => $this->userId(),
                'description'  => $description,
                'amount'       => (float)$run->net_pay,
                'gst_amount'   => 0,
                'total_amount' => (float)$run->net_pay,
                'expense_date' => $input['paid_date'],
                'method'       => $input['method'],
                'reference'    => $input['reference'] ?? null,
                'notes'        => $run->note ?? null,
            ]);

            DB::statement(
                "UPDATE payroll_runs
                 SET status = 'paid', paid_date = ?, method = ?, expense_id = ?
                 WHERE id = ?",
                [$input['paid_date'], $input['method'], $expense->id, $run->id]
            );

            $paid[] = [
                'id'         => (int)$run->id,
                'staff_name' => $staffName,
                'net_pay'    => (float)$run->net_pay,
                'expense_id' => (int)$expense->id,
            ];
        }

        return $this->success([
            'month' => $month,
            'year'  => $year,
            'count' => count($paid),
            'paid'  => $paid,
        ], count($paid) . ' payroll record(s) marked as paid.');
    }

    // ── Private helpers ───────────────────────────────────────────────────────

    private function findRun(int $id, int $businessId): object
    {
        $run = PayrollRun::find($id);
        if (!$run || (int)$run->business_id !== $businessId)
            $this->fail('Payroll record not found.', 404);
        return $run;
    }

    private function getOrCreateSalaryCategory(int $businessId): int
    {
        $cat = DB::selectOne(
            "SELECT id FROM expense_categories WHERE business_id = ? AND name = 'Salary' LIMIT 1",
            [$businessId]
        );

        if ($cat) {
            return (int)$cat->id;
        }

        $newCat = ExpenseCategory::create([
            'business_id' => $businessId,
            'name'        => 'Salary',
            'sort_order'  => 1,
        ]);

        return (int)$newCat->id;
    }
}
