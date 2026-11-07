@extends('layouts.admin')
@section('title',__('Support request'))
@section('page-title',__('Support'))
@section('content')
<x-ui.page-header :title="$ticket->subject" :description="'Status: '.ucwords(str_replace('_',' ',$ticket->status))" :eyebrow="__('Manage')"><x-ui.button :href="route('support.index')" variant="secondary" icon="back">{{ __('Back') }}</x-ui.button></x-ui.page-header>
@foreach($messages as $message)<x-ui.card :title="($message->actor?->role==='admin'?__('Administrator'):__('Business')).' · '.$message->created_at"><p class="app-message-text">{{ $message->body }}</p></x-ui.card>@endforeach
<x-ui.pagination :records="$messages" />
@if($ticket->status!=='closed'||auth()->user()->role==='admin')<x-ui.card :title="__('Reply')"><form method="POST" action="{{ route('support.reply',$ticket) }}" class="app-form-grid">@csrf<div class="app-span-full"><x-ui.field name="body" :label="__('Reply')" type="textarea" maxlength="6000" rows="4" required /></div>@if(auth()->user()->role==='admin')<x-ui.select name="status" :label="__('Ticket status')">@foreach(['open'=>__('Open'),'in_progress'=>__('In progress'),'closed'=>__('Closed')] as $value=>$label)<option value="{{ $value }}" @selected($ticket->status===$value)>{{ $label }}</option>@endforeach</x-ui.select>@endif<div class="app-actions"><x-ui.button>{{ __('Save reply') }}</x-ui.button></div></form></x-ui.card>@endif
@endsection
