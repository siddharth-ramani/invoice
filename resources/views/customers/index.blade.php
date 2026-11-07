@extends('layouts.admin')
@section('title', auth()->user()->role === 'admin' ? __('Vendors') : __('Customers'))
@section('page-title', auth()->user()->role === 'admin' ? __('Vendors') : __('Customers'))
@section('content')
@php $label = auth()->user()->role === 'admin' ? 'vendor' : 'customer'; @endphp
<x-ui.page-header :title="ucfirst($label).'s'" :description="$label === 'vendor' ? __('Manage the businesses using your platform.') : __('Keep customer contacts and billing details in one place.')" :eyebrow="__('Workspace')"><x-ui.button :href="route('customers.create')" icon="plus">Add {{ $label }}</x-ui.button></x-ui.page-header>
<x-ui.list-filters :action="route('customers.index')" />
<x-ui.card class="app-card-table">
@if($customers->isEmpty())
<x-ui.empty-state :title="'No '.$label.'s yet'" :description="'Add a '.$label.' to get started.'" icon="people"><x-ui.button :href="route('customers.create')" icon="plus">Add {{ $label }}</x-ui.button></x-ui.empty-state>
@else
<div class="app-table-scroll app-server-table-scroll"><table class="app-data-table" data-server-table><thead><tr><th scope="col">#</th><th scope="col">{{ __(ucfirst($label)) }}</th><th scope="col">{{ __('Email') }}</th><th scope="col">{{ __('Mobile') }}</th><th scope="col">{{ __('Actions') }}</th></tr></thead><tbody>
@foreach($customers as $customer)
<tr><td>{{ $customers->firstItem() + $loop->index }}</td><td class="app-table-title"><a href="{{ route('customers.show', $customer) }}">{{ $customer->name }}</a>@if($customer->gst_number)<span class="app-table-stack app-muted">GSTIN {{ $customer->gst_number }}</span>@endif</td><td>{{ $customer->email ?: __('Not provided') }}</td><td>{{ $customer->mobile ?: __('Not provided') }}</td><td><div class="app-actions">@if(auth()->user()->role === 'admin' && $customer->role === 'vendor')<form method="POST" action="{{ route('admin.vendors.login', $customer) }}">@csrf<x-ui.button icon="logout" class="btn-sm">{{ __('Login as vendor') }}</x-ui.button></form>@endif<x-ui.button :href="route('customers.show', $customer)" icon="eye" variant="secondary" class="btn-sm">{{ __('View') }}</x-ui.button><x-ui.button :href="route('customers.edit', $customer)" icon="edit" variant="secondary" class="btn-sm">{{ __('Edit') }}</x-ui.button><x-ui.delete-form :action="route('customers.destroy', $customer)" :message="'Delete '.$customer->name.'? Saved invoice documents may depend on these details.'" /></div></td></tr>
@endforeach
</tbody></table></div>
@endif
<x-ui.pagination :records="$customers" />
</x-ui.card>
@endsection
