@extends('layouts.admin')
@section('title', 'Edit '.(auth()->user()->role === 'admin' ? 'vendor' : 'customer'))
@section('page-title', auth()->user()->role === 'admin' ? __('Vendors') : __('Customers'))
@section('content')
<x-ui.page-header :title="'Edit '.(auth()->user()->role === 'admin' ? 'vendor' : 'customer')" :description="__('Keep billing details accurate and easy to find.')" :eyebrow="__('Workspace')"><x-ui.button :href="route('customers.index')" variant="secondary" icon="back">{{ __('Back to list') }}</x-ui.button></x-ui.page-header>
@include('customers.form')
@endsection