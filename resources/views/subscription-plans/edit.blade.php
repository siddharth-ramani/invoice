@extends('layouts.admin')
@section('title', __('Edit subscription plan'))
@section('page-title', __('Subscription plans'))
@section('content')
<x-ui.page-header :title="__('Edit subscription plan')" :description="__('Set clear pricing and limits for your businesses.')" :eyebrow="__('Administration')"><x-ui.button :href="route('subscription-plans.index')" variant="secondary" icon="back">{{ __('Back to plans') }}</x-ui.button></x-ui.page-header>
@include('subscription-plans.form')
@endsection