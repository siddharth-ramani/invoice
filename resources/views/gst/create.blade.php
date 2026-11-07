@extends('layouts.admin')
@section('title', __('Add GST rate'))
@section('page-title', __('GST rates'))
@section('content')
<x-ui.page-header :title="__('Add GST rate')" :description="__('Keep your business tax rates consistent.')" :eyebrow="__('Workspace')"><x-ui.button :href="route('gst.index')" variant="secondary" icon="back">{{ __('Back to rates') }}</x-ui.button></x-ui.page-header>
@include('gst.form')
@endsection