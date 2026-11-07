@extends('layouts.admin')
@section('title',__('Support'))
@section('page-title',__('Support'))
@section('content')
<x-ui.page-header :title="__('Support')" :description="__('Private business support requests and administrator replies.')" :eyebrow="__('Manage')" />
@if(auth()->user()->role==='vendor')<x-ui.card :title="__('Create support request')"><form method="POST" action="{{ route('support.store') }}" class="app-form-grid">@csrf<x-ui.field name="subject" :label="__('Subject')" maxlength="150" required /><div class="app-span-full"><x-ui.field name="body" :label="__('Message')" type="textarea" minlength="5" maxlength="6000" rows="4" required /></div><div class="app-actions"><x-ui.button>{{ __('Submit request') }}</x-ui.button></div></form></x-ui.card>@endif
<x-ui.card :title="__('Support history')" class="app-card-table"><div class="app-table-scroll app-server-table-scroll"><table class="app-data-table" data-server-table><thead><tr><th>{{ __('Subject') }}</th><th>{{ __('Status') }}</th><th>{{ __('Updated') }}</th><th>{{ __('Actions') }}</th></tr></thead><tbody>@forelse($tickets as $ticket)<tr><td>{{ $ticket->subject }}</td><td>{{ __(ucwords(str_replace('_',' ',$ticket->status))) }}</td><td>{{ $ticket->updated_at }}</td><td><x-ui.button :href="route('support.show',$ticket)" variant="secondary" class="btn-sm">{{ __('Open') }}</x-ui.button></td></tr>@empty<tr><td colspan="4">{{ __('No support requests.') }}</td></tr>@endforelse</tbody></table></div><x-ui.pagination :records="$tickets" /></x-ui.card>
@endsection
