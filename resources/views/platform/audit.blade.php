@extends('layouts.admin')
@section('title',__('Audit events'))
@section('page-title',__('Platform management'))
@section('content')
<x-ui.page-header :title="__('Audit events')" :description="__('Server-recorded administrative activity.')" :eyebrow="__('Administration')"><x-ui.button :href="route('platform.index')" variant="secondary" icon="back">{{ __('Back') }}</x-ui.button></x-ui.page-header>
<x-ui.card class="app-card-table" :title="__('Audit events')"><div class="app-table-scroll app-server-table-scroll"><table class="app-data-table" data-server-table><thead><tr><th>{{ __('When') }}</th><th>{{ __('Actor') }}</th><th>{{ __('Originating admin') }}</th><th>{{ __('Action') }}</th><th>{{ __('Target') }}</th></tr></thead><tbody>@forelse($events as $event)<tr><td>{{ $event->created_at }}</td><td>#{{ $event->actor_id }}</td><td>{{ $event->admin_actor_id?'#'.$event->admin_actor_id:'—' }}</td><td>{{ $event->action }}</td><td>{{ $event->target_type }} #{{ $event->target_id }}</td></tr>@empty<tr><td colspan="5">{{ __('No new platform audit events.') }}</td></tr>@endforelse</tbody></table></div><x-ui.pagination :records="$events" /></x-ui.card>
@endsection
