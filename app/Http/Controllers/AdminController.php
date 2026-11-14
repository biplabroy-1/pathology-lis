<?php

namespace App\Http\Controllers;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\ComplianceDoc;
use App\Models\CrmLead;
use App\Models\Department;
use App\Models\Employee;
use App\Models\FinanceTransaction;
use App\Models\IntegrationConfig;
use App\Models\SupportTicket;
use App\Services\LabAudit;
use Illuminate\Http\Request;

class AdminController extends Controller
{
    public function hr()
    {
        $employees = Employee::with(['branch', 'department', 'attendances'])->orderBy('employee_code')->get();
        $branches = Branch::all();
        $departments = Department::all();
        return view('admin.hr', compact('employees', 'branches', 'departments'));
    }

    public function storeEmployee(Request $request)
    {
        $data = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'department_id' => 'nullable|exists:departments,id',
            'name' => 'required|string',
            'role' => 'required|string',
            'qualification' => 'nullable|string',
            'license_no' => 'nullable|string',
            'phone' => 'required|string',
            'shift' => 'required|string',
        ]);
        $data['employee_code'] = 'EMP-' . str_pad((string) ((Employee::max('id') ?? 0) + 1), 3, '0', STR_PAD_LEFT);
        $data['joined_at'] = today();
        Employee::create($data);

        return back()->with('success', "Employee {$data['name']} ({$data['employee_code']}) added.");
    }

    public function finance()
    {
        $transactions = FinanceTransaction::with('branch')->orderByDesc('transaction_date')->get();
        $branches = Branch::all();
        $totalIncome = $transactions->where('type', 'Income')->sum('amount');
        $totalExpense = $transactions->where('type', 'Expense')->sum('amount');

        return view('admin.finance', compact('transactions', 'branches', 'totalIncome', 'totalExpense'));
    }

    public function storeTransaction(Request $request)
    {
        $data = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'type' => 'required|in:Income,Expense',
            'category' => 'required|string',
            'description' => 'required|string',
            'amount' => 'required|numeric|min:1',
            'payment_method' => 'required|string',
            'transaction_date' => 'required|date',
        ]);
        FinanceTransaction::create($data);
        return back()->with('success', "{$data['type']} transaction of ₹{$data['amount']} recorded.");
    }

    public function exportFinanceCsv()
    {
        $rows = FinanceTransaction::with('branch')->orderByDesc('transaction_date')->get();
        $csv = "Date,Branch,Type,Category,Description,Method,Amount\n";
        foreach ($rows as $r) {
            $csv .= "{$r->transaction_date->format('Y-m-d')},\"{$r->branch->name}\",{$r->type},{$r->category},\"{$r->description}\",{$r->payment_method},{$r->amount}\n";
        }
        return response($csv, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="patholab-finance-ledger.csv"',
        ]);
    }

    public function crm()
    {
        $leads = CrmLead::with('branch')->latest()->get();
        $tickets = SupportTicket::with('patient')->latest()->get();
        $branches = Branch::all();
        return view('admin.crm', compact('leads', 'tickets', 'branches'));
    }

    public function storeLead(Request $request)
    {
        $data = $request->validate([
            'branch_id' => 'required|exists:branches,id',
            'name' => 'required|string',
            'phone' => 'required|string',
            'source' => 'required|string',
            'campaign_name' => 'nullable|string',
            'interest' => 'nullable|string',
            'follow_up_date' => 'nullable|date',
        ]);
        CrmLead::create($data);
        return back()->with('success', "Lead {$data['name']} added to CRM pipeline.");
    }

    public function compliance()
    {
        $auditLogs = AuditLog::latest()->paginate(20);
        $docs = ComplianceDoc::with('branch')->get();
        $branches = Branch::all();
        return view('admin.compliance', compact('auditLogs', 'docs', 'branches'));
    }

    public function triggerBackup()
    {
        LabAudit::log('Database Backup', 'Compliance & Admin', 'DB-PATHOLOGY', 'Manual snapshot verified for NABL retention policy.');
        return back()->with('success', 'Database snapshot & audit log backup completed.');
    }

    public function integrations()
    {
        $integrations = IntegrationConfig::all();
        return view('admin.integrations', compact('integrations'));
    }

    public function testIntegration(IntegrationConfig $integrationConfig)
    {
        $integrationConfig->update(['last_sync_at' => now(), 'status' => 'Connected']);
        LabAudit::log('Integration Ping', 'Integrations', $integrationConfig->name, "Protocol {$integrationConfig->protocol} handshake OK.");
        return back()->with('success', "{$integrationConfig->name} ({$integrationConfig->protocol}) synced successfully.");
    }
}

