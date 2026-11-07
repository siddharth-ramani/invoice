@extends('layouts.admin')
@section('title', __('Create invoice'))
@section('page-title', __('Invoices'))
@section('content')
<x-ui.page-header :title="__('Create invoice')" :description="__('Clear billing details, organised in one place.')" :eyebrow="__('Workspace')"><x-ui.button :href="route('invoices.index')" variant="secondary" icon="back">{{ __('Back to invoices') }}</x-ui.button></x-ui.page-header>
@include('invoices.form')
@endsection