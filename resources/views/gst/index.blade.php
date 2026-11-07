@extends('layouts.admin')
@section('title', __('GST rates'))
@section('page-title', __('GST rates'))
@section('content')
<x-ui.page-header :title="__('GST rates')" :description="__('Manage the tax rates saved for your business.')" :eyebrow="__('Workspace')"><x-ui.button :href="route('gst.create')" icon="plus">{{ __('Add GST rate') }}</x-ui.button></x-ui.page-header>
<x-ui.card class="app-card-table">
@if($slabs->isEmpty())<x-ui.empty-state :title="__('No GST rates saved')" :description="__('Add a rate to keep your business tax settings organised.')" icon="tax"><x-ui.button :href="route('gst.create')" icon="plus">{{ __('Add GST rate') }}</x-ui.button></x-ui.empty-state>
@else
<div class="app-table-scroll"><table class="app-data-table"><thead><tr><th scope="col">#</th><th scope="col">{{ __('Name') }}</th><th scope="col">{{ __('Rate') }}</th><th scope="col">{{ __('Actions') }}</th></tr></thead><tbody>
@foreach($slabs as $gst)<tr><td>{{ $loop->iteration }}</td><td class="app-table-title">{{ $gst->name }}</td><td>{{ number_format($gst->rate, 2) }}%</td><td><div class="app-actions"><x-ui.button :href="route('gst.edit', $gst)" variant="secondary" icon="edit" class="btn-sm">{{ __('Edit') }}</x-ui.button><x-ui.delete-form :action="route('gst.destroy', $gst)" message="Delete this saved GST rate? Existing invoice totals will remain unchanged." /></div></td></tr>@endforeach
</tbody></table></div>
@endif
</x-ui.card>
@endsection
