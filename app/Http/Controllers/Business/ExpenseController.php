<?php

namespace App\Http\Controllers\Business;

use App\Http\Controllers\Controller;
use App\Http\Controllers\PaymentController;
use App\Http\Requests\Business\ExpenseRequest;
use App\Http\Requests\Business\ListRequest;
use App\Models\Expense;
use App\Models\ExpenseAttachment;
use App\Models\ExpenseCategory;
use App\Services\Business\ExpenseWriter;
use App\Services\Business\Guard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

class ExpenseController extends Controller
{
    private function query(ListRequest $r)
    {
        return Expense::where('vendor_id', $r->user()->id)->when($r->validated('status') === 'void', fn ($q) => $q->whereNotNull('voided_at'), fn ($q) => $q->whereNull('voided_at'))->when($r->validated('from_date'), fn ($q, $d) => $q->where('expense_date', '>=', $d))->when($r->validated('to_date'), fn ($q, $d) => $q->where('expense_date', '<=', $d))->when($r->validated('category_id'), fn ($q, $id) => $q->where('expense_category_id', $id))->when($r->validated('q'), fn ($q, $s) => $q->where(fn ($q) => $q->where('reference', 'like', '%'.$s.'%')->orWhere('notes', 'like', '%'.$s.'%')));
    }

    public function index(ListRequest $request)
    {
        $query = $this->query($request);
        $summary = (clone $query)->selectRaw('COUNT(*) AS count, COALESCE(SUM(ROUND(amount*100,0)),0) AS amount')->first();
        $expenses = $query->with('category')->orderByDesc('expense_date')->orderByDesc('id')->paginate(20)->withQueryString();
        $categories = ExpenseCategory::where('vendor_id', $request->user()->id)->orderBy('name')->get();

        return view('expenses.index', compact('expenses', 'summary', 'categories'));
    }

    public function create()
    {
        $categories = ExpenseCategory::where('vendor_id', auth()->id())->where('is_active', true)->orderBy('name')->get();

        return view('expenses.form', compact('categories'));
    }

    public function store(ExpenseRequest $request)
    {
        $saved = app(ExpenseWriter::class)->save($request->user(), $request->validated(), null, $request->file('attachment'));

        return redirect()->route('expenses.show', $saved)->with('success', 'Expense saved.');
    }

    public function show(Expense $expense)
    {
        $this->authorize('view', $expense);
        $expense->load('category', 'attachments');
        $events = $expense->events()->orderByDesc('id')->paginate(10);

        return view('expenses.show', compact('expense', 'events'));
    }

    public function edit(Expense $expense)
    {
        $this->authorize('update', $expense);
        abort_if($expense->voided_at, 403);
        $categories = ExpenseCategory::where('vendor_id', auth()->id())->orderBy('name')->get();

        return view('expenses.form', compact('expense', 'categories'));
    }

    public function update(ExpenseRequest $request, Expense $expense)
    {
        $saved = app(ExpenseWriter::class)->save($request->user(), $request->validated(), $expense, $request->file('attachment'));

        return redirect()->route('expenses.show', $saved)->with('success', 'Expense updated; earlier values retained in history.');
    }

    public function void(Request $request, Expense $expense)
    {
        $this->authorize('update', $expense);
        $data = $request->validate(['revision' => 'required|integer|min:1', 'reason' => 'required|string|min:5|max:1000', 'confirm' => 'accepted']);
        app(ExpenseWriter::class)->void($request->user(), $expense, (int) $data['revision'], $data['reason']);

        return redirect()->route('expenses.show', $expense)->with('success', 'Expense voided. Original history and attachments retained.');
    }

    public function category(Request $request)
    {
        $data = $request->validate(['name' => 'required|string|max:100']);
        DB::transaction(function () use ($request, $data) {
            Guard::vendor($request->user());
            if (ExpenseCategory::where('vendor_id', $request->user()->id)->where('name', $data['name'])->exists()) {
                Guard::fail('name', 'This category already exists.');
            }ExpenseCategory::create(['vendor_id' => $request->user()->id, 'name' => $data['name']]);
        }, 5);

        return back()->with('success', 'Expense category created.');
    }

    public function attachment(ExpenseAttachment $attachment)
    {
        $this->authorize('view', $attachment->expense);
        abort_unless(Storage::disk('local')->exists($attachment->path), 404);

        return Storage::disk('local')->download($attachment->path, $attachment->original_name, ['Content-Type' => $attachment->mime, 'X-Content-Type-Options' => 'nosniff']);
    }

    public function export(ListRequest $request)
    {
        $query = $this->query($request)->with('category');

        return response()->streamDownload(function () use ($query) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Date', 'Category', 'Amount INR', 'Method', 'Reference', 'Notes']);
            foreach ($query->lazyById(100) as $r) {
                fputcsv($out, [$r->expense_date, PaymentController::csv($r->category->name), $r->amount, $r->method, PaymentController::csv($r->reference), PaymentController::csv($r->notes)]);
            }fclose($out);
        }, 'expenses.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
