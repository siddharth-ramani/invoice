<?php

namespace App\Http\Controllers;

use App\Http\Requests\ContactListRequest;
use App\Http\Requests\CustomerHistoryRequest;
use App\Http\Requests\CustomerRequest;
use App\Models\Invoice;
use App\Models\Payment;
use App\Models\Quotation;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Business\Modules;
use App\Services\Payments\PaymentModule;
use App\Services\Payments\PaymentQueries;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class CustomerController extends Controller
{
    public function index(ContactListRequest $request)
    {
        $user = $request->user();
        $query = User::when($user->role === 'admin', fn ($q) => $q->where('role', 'vendor'), fn ($q) => $q->where('role', 'customer')->where('created_by', $user->id));
        if ($q = $request->validated('q')) {
            $query->where(fn ($query) => $query->where('name', 'like', '%'.$q.'%')->orWhere('email', 'like', '%'.$q.'%')->orWhere('mobile', 'like', '%'.$q.'%')->orWhere('gst_number', 'like', '%'.$q.'%'));
        }
        $sort = $request->validated('sort') ?? 'newest';
        $customers = $query->orderBy($sort === 'name' ? 'name' : 'id', $sort === 'newest' ? 'desc' : 'asc')->orderBy('id')->paginate(20)->withQueryString();

        return view('customers.index', compact('customers'));
    }

    public function show(CustomerHistoryRequest $request, User $customer)
    {
        $isBusiness = $customer->role === 'vendor';
        $query = Invoice::query();
        if ($isBusiness) {
            $query->where('vendor_id', $customer->id);
        } else {
            $query->where('customer_id', $customer->id)->where('vendor_id', $request->user()->role === 'vendor' ? $request->user()->id : $customer->created_by);
        }
        if ($q = $request->validated('q')) {
            $query->where('invoice_no', 'like', '%'.$q.'%');
        }
        if ($date = $request->validated('from_date')) {
            $query->whereDate('invoice_date', '>=', $date);
        }
        if ($date = $request->validated('to_date')) {
            $query->whereDate('invoice_date', '<=', $date);
        }
        if ($status = $request->validated('status')) {
            $query->where('status', $status);
        }
        $summary = (clone $query)->selectRaw('COUNT(*) AS invoice_count, COALESCE(SUM(grand_total),0) AS sales')->first();
        $invoices = $query->with('customer')->orderByDesc('invoice_date')->orderByDesc('id')->paginate(20)->withQueryString();

        $paymentTracking = ! $isBusiness && $request->user()->role === 'vendor' && app(PaymentModule::class)->ready();
        $balances = $paymentTracking ? app(PaymentQueries::class)->confirmedTotals($request->user()->id, $customer->id) : null;

        return view('customers.show', compact('customer', 'invoices', 'summary', 'isBusiness', 'paymentTracking', 'balances'));
    }

    public function create()
    {
        $this->authorize('create', User::class);

        return view('customers.create');
    }

    public function store(CustomerRequest $request)
    {
        $data = $request->validated();
        $data['role'] = $request->user()->role === 'admin' ? 'vendor' : 'customer';
        $data['created_by'] = $request->user()->id;
        $data['password'] = Hash::make($request->filled('password') ? $data['password'] : Str::random(40));
        DB::transaction(fn () => User::create($data));

        return redirect()->route('customers.index')->with('success', 'Contact created successfully.');
    }

    public function edit(User $customer)
    {
        $this->authorize('update', $customer);

        return view('customers.edit', compact('customer'));
    }

    public function update(CustomerRequest $request, User $customer)
    {
        $data = $request->validated();
        if ($request->filled('password')) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }
        DB::transaction(function () use ($request, $customer, $data) {
            $saved = $this->lockContact($request->user(), $customer);
            $this->authorize('update', $saved);
            $saved->update($data);
        }, 5);

        return redirect()->route('customers.index')->with('success', 'Contact updated successfully.');
    }

    public function destroy(User $customer)
    {
        $this->authorize('delete', $customer);
        $deleted = DB::transaction(function () use ($customer) {
            $saved = $this->lockContact(auth()->user(), $customer);
            $this->authorize('delete', $saved);
            foreach (['products', 'expense_categories', 'expenses', 'inventory_settings', 'stock_movements'] as $table) {
                if (app(Modules::class)->installed($table) && DB::table($table)->where('vendor_id', $saved->id)->exists()) {
                    return false;
                }
            }
            foreach (['user_preferences' => 'user_id', 'account_controls' => 'user_id', 'subscription_events' => 'vendor_id', 'billing_records' => 'vendor_id', 'ai_requests' => 'vendor_id', 'support_tickets' => 'vendor_id', 'business_notifications' => 'user_id', 'notification_deliveries' => 'user_id'] as $table => $column) {
                if (app(Modules::class)->installed($table) && DB::table($table)->where($column, $saved->id)->exists()) {
                    return false;
                }
            }
            if (app(Modules::class)->installed('quotations') && Quotation::where(fn ($q) => $q->where('customer_id', $saved->id)->orWhere('vendor_id', $saved->id))->exists()) {
                return false;
            }
            if ((app(PaymentModule::class)->ready() && Payment::where(fn ($q) => $q->where('customer_id', $saved->id)->orWhere('vendor_id', $saved->id))->exists())
                || Invoice::withTrashed()->where(fn ($q) => $q->where('customer_id', $saved->id)->orWhere('vendor_id', $saved->id))->exists()
                || Subscription::where('user_id', $saved->id)->exists() || User::where('created_by', $saved->id)->exists()) {
                return false;
            }
            $saved->delete();

            return true;
        }, 5);

        return $deleted ? redirect()->route('customers.index')->with('success', 'Contact deleted.') : back()->with('error', 'This contact is linked to invoices, subscriptions or other contacts and cannot be deleted. Its billing history must remain available.');
    }

    private function lockContact(User $actor, User $contact): User
    {
        $owner = $contact->role === 'vendor' ? $contact->id : ($actor->role === 'vendor' ? $actor->id : $contact->created_by);
        if ($owner) {
            User::whereKey($owner)->lockForUpdate()->first();
        }

        return User::whereKey($contact->id)->lockForUpdate()->firstOrFail();
    }
}
