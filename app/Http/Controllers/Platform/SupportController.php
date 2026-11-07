<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\SupportTicket;
use App\Services\Business\Guard;
use App\Services\Business\Notifications;
use App\Services\Business\Platform;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class SupportController extends Controller
{
    private function access(SupportTicket $ticket): void
    {
        abort_unless(auth()->user()->role === 'admin' || (auth()->user()->role === 'vendor' && (int) $ticket->vendor_id === auth()->id()), 403);
    }

    public function index(Request $r)
    {
        abort_unless(in_array($r->user()->role, ['admin', 'vendor'], true), 403);
        $r->validate(['status' => 'nullable|in:open,in_progress,closed']);
        $tickets = SupportTicket::when($r->user()->role !== 'admin', fn ($q) => $q->where('vendor_id', $r->user()->id))->when($r->status, fn ($q, $s) => $q->where('status', $s))->orderByDesc('id')->paginate(20)->withQueryString();

        return view('support.index', compact('tickets'));
    }

    public function store(Request $r)
    {
        abort_unless($r->user()->role === 'vendor', 403);
        $data = $r->validate(['subject' => 'required|string|max:150', 'body' => 'required|string|min:5|max:6000']);
        $ticket = DB::transaction(function () use ($r, $data) {
            Guard::vendor($r->user());
            $ticket = SupportTicket::create(['vendor_id' => $r->user()->id, 'subject' => $data['subject']]);
            $ticket->messages()->create(['actor_id' => $r->user()->id, 'body' => $data['body']]);
            app(Platform::class)->audit('support.created', 'support_ticket', $ticket->id, [], $ticket->vendor_id);

            return $ticket;
        }, 5);

        return redirect()->route('support.show', $ticket)->with('success', 'Support request created.');
    }

    public function show(SupportTicket $ticket)
    {
        $this->access($ticket);
        $messages = $ticket->messages()->with('actor')->orderBy('id')->paginate(20);

        return view('support.show', compact('ticket', 'messages'));
    }

    public function reply(Request $r, SupportTicket $ticket)
    {
        $this->access($ticket);
        $data = $r->validate(['body' => 'required|string|min:1|max:6000', 'status' => 'nullable|in:open,in_progress,closed']);
        DB::transaction(function () use ($r, $ticket, $data) {
            $saved = SupportTicket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();
            if ($saved->status === 'closed' && $r->user()->role !== 'admin') {
                Guard::fail('body', 'This ticket is closed. Open a new support request.');
            }if (! empty($data['status']) && $r->user()->role !== 'admin') {
                abort(403);
            }$saved->messages()->create(['actor_id' => $r->user()->id, 'body' => $data['body']]);
            if ($r->user()->role === 'admin' && ! empty($data['status'])) {
                $saved->update(['status' => $data['status']]);
            }app(Platform::class)->audit('support.replied', 'support_ticket', $saved->id, ['status' => $saved->status], $saved->vendor_id);
            if ($r->user()->role === 'admin') {
                app(Notifications::class)->add($saved->vendor_id, 'support-reply:'.$saved->messages()->max('id'), 'Support reply', 'The administrator replied to your support request.', route('support.show', $saved, false));
            }
        }, 5);

        return back()->with('success', 'Reply saved.');
    }
}
